<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Users\DeleteAccount;
use App\Mail\Account\NoticeDecided;
use App\Mail\Account\NoticeIssued;
use App\Mail\Support\SupportAnswer;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\Notice;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeBillingGateway;
use Tests\TestCase;

/**
 * Support, statements of reasons with appeals, and Plus refunds (contract/api.md, "Support,
 * notices and refunds (L)").
 */
final class SupportAndNoticesTest extends TestCase
{
    use RefreshDatabase;

    private function live(User $owner): Harmony
    {
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id, 'title' => 'Silent Night for Mum']);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    public function test_anyone_writes_to_support_with_the_context_they_chose_and_hillel_answers_by_email(): void
    {
        Mail::fake();
        $sent = $this->postJson('/api/support', ['category' => 'recording', 'message' => 'My takes come out late.', 'email' => 'ana@example.com', 'context' => ['appVersion' => '2026.11.1', 'page' => '/sing', 'timingCheck' => ['latencyMs' => 112]]]);
        $this->assertMatchesContract($sent, 'support.create', 201);
        $this->assertMatchesContract($this->postJson('/api/support', ['category' => 'recording', 'message' => 'No address.']), 'support.create', 422);

        // The honeypot: answered as any message is, and nothing kept.
        $this->postJson('/api/support', ['category' => 'other', 'message' => 'Buy now', 'email' => 'bot@example.com', 'website' => 'x'])->assertCreated();
        $this->assertSame(1, SupportMessage::query()->count());

        $admin = $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/support');
        $this->assertMatchesContract($list, 'admin.support.list', 200);
        $id = (string) $list->json('data.0.id');
        $shown = $this->getJson('/api/admin/support/'.$id);
        $this->assertMatchesContract($shown, 'admin.support.show', 200);
        $shown->assertJsonPath('data.context.timingCheck.latencyMs', 112)->assertJsonPath('data.savedReplies.0.key', 'silent_mic');
        $this->assertMatchesContract($this->getJson('/api/admin/support/nope'), 'admin.support.show', 404);

        $this->assertMatchesContract($this->postJson('/api/admin/support/'.$id.'/reply', ['body' => '']), 'admin.support.reply', 422);
        $answered = $this->postJson('/api/admin/support/'.$id.'/reply', ['body' => "Thanks.\n\nTry the timing check again."]);
        $this->assertMatchesContract($answered, 'admin.support.reply', 200);
        $answered->assertJsonPath('data.status', 'answered')->assertJsonPath('data.replies.0.by.id', $admin->sqid());
        Mail::assertQueued(SupportAnswer::class, fn (SupportAnswer $mail): bool => $mail->hasTo('ana@example.com'));
        $this->assertMatchesContract($this->postJson('/api/admin/support/nope/reply', ['body' => 'x y']), 'admin.support.reply', 404);

        $this->assertMatchesContract($this->patchJson('/api/admin/support/'.$id, ['status' => 'closed']), 'admin.support.update', 200);
        // Who closed it, and when, is in the audit log.
        $this->assertTrue(AuditLog::query()->where('action', 'support.status')->whereNotNull('actor_id')->exists());
        $this->assertMatchesContract($this->patchJson('/api/admin/support/'.$id, ['status' => 'gone']), 'admin.support.update', 422);
        $this->assertMatchesContract($this->patchJson('/api/admin/support/nope', ['status' => 'closed']), 'admin.support.update', 404);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/support', ['category' => 'other', 'message' => 'Again '.$i, 'email' => 'ana@example.com']);
        }

        $this->assertMatchesContract($this->postJson('/api/support', ['category' => 'other', 'message' => 'One more', 'email' => 'ana@example.com']), 'support.create', 429);
    }

    public function test_support_messages_go_two_years_after_their_last_word_or_with_the_account(): void
    {
        $old = SupportMessage::factory()->create(['updated_at' => now()->subMonths(25)]);
        $recent = SupportMessage::factory()->create(['updated_at' => now()->subMonths(23)]);
        $this->artisan('app:accounts:prune')->expectsOutputToContain('1 support messages')->assertSuccessful();
        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());

        $ana = User::factory()->create(['email' => 'ana@example.com'])->fresh() ?? throw new \LogicException;
        $signedIn = SupportMessage::factory()->create(['user_id' => $ana->id]);
        $signedOut = SupportMessage::factory()->create(['user_id' => null, 'email' => 'ana@example.com']);
        app(DeleteAccount::class)->handle($ana, '203.0.113.9');
        $this->assertNull($signedIn->fresh());
        $this->assertNull($signedOut->fresh());
        $this->assertNotNull($recent->fresh());
    }

    public function test_a_hidden_harmony_comes_with_its_reasons_and_a_reversed_appeal_restores_it(): void
    {
        $owner = User::factory()->create();
        $harmony = $this->live($owner);
        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/harmonies/'.$harmony->public_id.'/hide', ['reason' => Harmony::HIDDEN_ADMIN, 'note' => 'The title broke the guidelines.'])->assertOk();

        $this->actingAs($owner);
        $list = $this->getJson('/api/notices');
        $this->assertMatchesContract($list, 'notices.list', 200);
        $list->assertJsonPath('data.0.kind', 'harmony_hidden')->assertJsonPath('data.0.rule', 'community_guidelines')->assertJsonPath('data.0.note', 'The title broke the guidelines.')->assertJsonPath('data.0.automatic', false);
        $id = (string) $list->json('data.0.id');
        $this->assertMatchesContract($this->getJson('/api/notices/'.$id), 'notices.show', 200);
        $this->assertMatchesContract($this->postJson('/api/notices/'.$id.'/appeal', ['text' => '']), 'notices.appeal', 422);
        $appealed = $this->postJson('/api/notices/'.$id.'/appeal', ['text' => 'It\'s my own carol.']);
        $this->assertMatchesContract($appealed, 'notices.appeal', 200);
        $this->assertMatchesContract($this->postJson('/api/notices/'.$id.'/appeal', ['text' => 'Again.']), 'notices.appeal', 409);

        // Someone else finds nothing.
        $this->actingAs(User::factory()->create());
        $this->assertMatchesContract($this->getJson('/api/notices/'.$id), 'notices.show', 404);
        $this->assertMatchesContract($this->postJson('/api/notices/'.$id.'/appeal', ['text' => 'Mine?']), 'notices.appeal', 404);

        $this->actingAsAdmin($admin);
        $waiting = $this->getJson('/api/admin/appeals');
        $this->assertMatchesContract($waiting, 'admin.appeals.list', 200);
        $waiting->assertJsonPath('data.0.person.id', $owner->sqid());
        $this->assertMatchesContract($this->postJson('/api/admin/appeals/'.$id, ['decision' => 'maybe', 'note' => 'x']), 'admin.appeals.decide', 422);
        $decided = $this->postJson('/api/admin/appeals/'.$id, ['decision' => 'reversed', 'note' => 'A carol in the public domain.']);
        $this->assertMatchesContract($decided, 'admin.appeals.decide', 200);
        $this->assertNull($harmony->refresh()->hidden_at);
        $this->assertMatchesContract($this->postJson('/api/admin/appeals/'.$id, ['decision' => 'kept', 'note' => 'x y']), 'admin.appeals.decide', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/appeals/nope', ['decision' => 'kept', 'note' => 'x y']), 'admin.appeals.decide', 404);
    }

    public function test_a_reversal_that_cant_be_undone_is_refused_and_nothing_is_recorded(): void
    {
        $owner = User::factory()->create();
        $harmony = $this->live($owner);
        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/harmonies/'.$harmony->public_id.'/hide', ['reason' => Harmony::HIDDEN_ADMIN, 'note' => 'The title broke the guidelines.'])->assertOk();
        $hidden = Notice::query()->sole();
        $this->actingAs($owner);
        $this->postJson('/api/notices/'.$hidden->sqid().'/appeal', ['text' => 'It\'s my own carol.'])->assertOk();

        // Removed for good since: reversing the hide can't bring it back.
        $this->actingAsAdmin($admin);
        $this->postJson('/api/admin/harmonies/'.$harmony->public_id.'/remove', ['reason' => 'dmca', 'note' => 'No counter-notice.'])->assertOk();
        $refused = $this->postJson('/api/admin/appeals/'.$hidden->sqid(), ['decision' => 'reversed', 'note' => 'A carol in the public domain.']);
        $this->assertMatchesContract($refused, 'admin.appeals.decide', 409);
        $refused->assertJsonPath('code', 'cannot_reverse');
        $this->assertNull($hidden->refresh()->decided_at);
        $this->postJson('/api/admin/appeals/'.$hidden->sqid(), ['decision' => 'kept', 'note' => 'Removed after a copyright notice.'])->assertOk();
    }

    public function test_a_suspended_person_gets_the_notice_by_email_and_can_appeal_by_its_link(): void
    {
        Mail::fake();
        $person = User::factory()->create(['email' => 'sam@example.com']);
        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/people/'.$person->sqid().'/suspend', ['reason' => 'Spam links in titles.'])->assertOk();

        $link = '';
        Mail::assertQueued(NoticeIssued::class, function (NoticeIssued $mail) use (&$link): bool {
            $link = $mail->link;

            return $mail->hasTo('sam@example.com');
        });
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $token = (string) $query['token'];
        $this->assertStringStartsWith('https://stackapella.test/notice?token=', $link);
        $this->assertSame(40, strlen($token));

        $this->post('/api/auth/sign-out');
        $this->getJson('/api/notices/'.$token)->assertOk()->assertJsonPath('data.kind', 'account_suspended')->assertJsonPath('data.note', 'Spam links in titles.');
        $this->postJson('/api/notices/'.$token.'/appeal', ['text' => 'Those were my choir\'s links.'])->assertOk()->assertJsonPath('data.canAppeal', false);
        $this->assertNotNull(Notice::query()->sole()->appealed_at);
        // The email carried the facts and where else to take it.
        Mail::assertQueued(NoticeIssued::class, fn (NoticeIssued $mail): bool => $mail->facts === 'Spam links in titles.' && str_contains($mail->render(), 'out-of-court dispute settlement body'));

        // Hillel's answer reaches them by email too: they still can't sign in to read it.
        $this->actingAsAdmin($admin);
        $this->postJson('/api/admin/appeals/'.Notice::query()->sole()->sqid(), ['decision' => 'kept', 'note' => 'The links were to a spam site.'])->assertOk();
        Mail::assertQueued(NoticeDecided::class, fn (NoticeDecided $mail): bool => $mail->hasTo('sam@example.com') && $mail->decision === 'kept' && str_contains($mail->render(), 'The links were to a spam site.'));
    }

    public function test_support_needs_turnstile_in_shield_mode(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret' => 'secret']);
        app(Flags::class)->set('shield_mode', 'on', null, 'A flood');
        $message = ['category' => 'other', 'message' => 'Hello there.', 'email' => 'ana@example.com'];

        $this->assertMatchesContract($this->postJson('/api/support', $message), 'support.create', 403);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        $failed = $this->postJson('/api/support', [...$message, 'turnstileToken' => 'bad']);
        $this->assertMatchesContract($failed, 'support.create', 403);
        $failed->assertJsonPath('code', 'turnstile_failed');
    }

    public function test_hillel_refunds_plus_within_what_was_paid(): void
    {
        $stripe = new FakeBillingGateway;
        $this->app->instance(BillingGateway::class, $stripe);
        $person = User::factory()->create(['stripe_id' => 'cus_1']);
        $this->actingAsAdmin();

        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$person->sqid().'/refund', ['amountCents' => 100, 'note' => 'Asked within 14 days.']), 'admin.people.refund', 409);

        $stripe->latest = ['paymentIntent' => 'pi_plus', 'cents' => 3999, 'refunded' => 0, 'currency' => 'gbp', 'paidAt' => now()->getTimestamp()];
        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$person->sqid().'/refund', ['amountCents' => 5000, 'note' => 'Too much.']), 'admin.people.refund', 422);
        $refunded = $this->postJson('/api/admin/people/'.$person->sqid().'/refund', ['amountCents' => 3999, 'note' => 'Asked within 14 days.']);
        $this->assertMatchesContract($refunded, 'admin.people.refund', 200);
        $this->assertSame([['pi_plus', 3999, 'plus-refund-pi_plus-0-3999']], $stripe->refunds);
    }

    public function test_a_refund_sent_twice_refunds_once(): void
    {
        $stripe = new FakeBillingGateway;
        $this->app->instance(BillingGateway::class, $stripe);
        $person = User::factory()->create(['stripe_id' => 'cus_1']);
        $this->actingAsAdmin();
        $stripe->latest = ['paymentIntent' => 'pi_plus', 'cents' => 3999, 'refunded' => 0, 'currency' => 'gbp', 'paidAt' => now()->getTimestamp()];
        $refund = fn (int $cents) => $this->postJson('/api/admin/people/'.$person->sqid().'/refund', ['amountCents' => $cents, 'note' => 'Half, as agreed.']);

        // A double click: the second answers as the first did.
        $first = $refund(2000)->assertOk()->json();
        $this->assertSame($first, $refund(2000)->assertOk()->json());
        $this->assertSame([['pi_plus', 2000, 'plus-refund-pi_plus-0-2000']], $stripe->refunds);
        $this->assertSame(1, AuditLog::query()->where('action', 'person.plus_refunded')->count());

        // A different amount is a new refund, within what's left.
        $refund(1999)->assertOk()->assertJsonPath('data.refundedCents', 3999);
        $refund(1)->assertUnprocessable();
        $this->assertCount(2, $stripe->refunds);
    }

    public function test_a_message_may_be_about_something_broken_or_an_idea(): void
    {
        $this->postJson('/api/support', ['category' => 'bug', 'message' => 'The mixer froze.', 'email' => 'sam@example.net'])->assertCreated();
        $this->postJson('/api/support', ['category' => 'idea', 'message' => 'A metronome, please.', 'email' => 'sam@example.net'])->assertCreated();
        $this->assertSame(['bug', 'idea'], SupportMessage::query()->orderBy('id')->pluck('category')->all());
    }

    public function test_the_refund_panel_reads_the_latest_plus_payment(): void
    {
        $stripe = new FakeBillingGateway;
        $this->app->instance(BillingGateway::class, $stripe);
        $person = User::factory()->create(['stripe_id' => 'cus_1']);
        $this->actingAsAdmin();

        $none = $this->getJson('/api/admin/people/'.$person->sqid().'/payment');
        $this->assertMatchesContract($none, 'admin.people.payment', 200);
        $none->assertJsonPath('data.payment', null);

        $stripe->latest = ['paymentIntent' => 'pi_plus', 'cents' => 3999, 'refunded' => 1000, 'currency' => 'gbp', 'paidAt' => 1792400000];
        $paid = $this->getJson('/api/admin/people/'.$person->sqid().'/payment');
        $this->assertMatchesContract($paid, 'admin.people.payment', 200);
        $paid->assertJsonPath('data.payment.amountCents', 3999)->assertJsonPath('data.payment.refundedCents', 1000)->assertJsonPath('data.payment.paidAt', '2026-10-19T08:53:20Z');

        $this->assertMatchesContract($this->getJson('/api/admin/people/nope/payment'), 'admin.people.payment', 404);
    }

    public function test_a_message_from_an_account_shows_their_plan(): void
    {
        $ana = User::factory()->create(['plus_paid_until' => now()->addMonth()]);
        $this->actingAs($ana)->postJson('/api/support', ['category' => 'payments', 'message' => 'Was I charged twice?'])->assertCreated();

        $this->actingAsAdmin();
        $id = (string) $this->getJson('/api/admin/support')->assertOk()->json('data.0.id');
        $shown = $this->getJson('/api/admin/support/'.$id);
        $this->assertMatchesContract($shown, 'admin.support.show', 200);
        $shown->assertJsonPath('data.from.account.id', $ana->sqid())->assertJsonPath('data.from.account.plan.plus', true);
        $this->getJson('/api/admin/support')->assertJsonMissingPath('data.0.from.account.plan');
    }

    public function test_an_account_without_an_email_still_sends_diagnostics(): void
    {
        Mail::fake();
        $kim = User::factory()->create(['email' => null, 'email_verified_at' => null])->fresh() ?? throw new \LogicException;
        $sent = $this->actingAs($kim)->postJson('/api/support', ['category' => 'diagnostics', 'message' => 'The mic cuts out.', 'context' => ['appVersion' => '2026.11.1', 'audioErrors' => ['NotReadableError: the mic stopped']]]);
        $this->assertMatchesContract($sent, 'support.create', 201);
        $this->assertSame(['NotReadableError: the mic stopped'], SupportMessage::query()->sole()->context['audioErrors']);

        // Hillel's answer is kept; there's no address to email it to.
        $this->actingAsAdmin();
        $id = SupportMessage::query()->sole()->sqid();
        $this->postJson('/api/admin/support/'.$id.'/reply', ['body' => 'Thanks, looking into it.'])->assertOk();
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }
}
