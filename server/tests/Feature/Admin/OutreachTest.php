<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\Outreach\OutreachEmail;
use App\Models\AuditLog;
use App\Models\EmailSuppression;
use App\Models\OutreachMessage;
use App\Models\OutreachReply;
use App\Models\OutreachSuppression;
use App\Models\OutreachTarget;
use App\Models\User;
use App\Services\EventSchedule\Signature;
use App\Support\Outreach\Outreach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Outreach (docs/outreach-plan.md): the research list in, a draft Hillel edits, one email per press
 * under the cap and the suppression list, one follow-up after ten days at most, what came back
 * (deliveries, bounces, replies with their text), and what each organization's link brought from
 * the anonymous counts. No pixel, no per-person link.
 */
final class OutreachTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00', 'UTC'));
        config(['outreach.from' => 'hillel@hello.example.com', 'outreach.token' => 'token', 'outreach.account_id' => 'account', 'outreach.webhook_secret' => 'secret', 'outreach.daily_cap' => 5]);
        Mail::fake();
        $this->admin = $this->actingAsAdmin();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function row(array $row = []): array
    {
        return ['name' => 'Harmony Society', 'group' => 'Barbershop', 'country' => 'us', 'reach' => '19,000 members', 'fit' => 'Their chapters learn parts at home before rehearsal.',
            'contact' => 'outreach@harmony.example.com', 'source' => 'https://harmony.example.com/contact', 'angle' => 'choir_groups', ...$row];
    }

    private function target(array $row = []): OutreachTarget
    {
        $made = $this->postJson('/api/admin/outreach', self::row($row));
        $this->assertMatchesContract($made, 'admin.outreach.create', 201);

        return OutreachTarget::query()->latest('id')->firstOrFail();
    }

    private function draftAndSend(OutreachTarget $target, string $kind = 'first'): TestResponse
    {
        $this->assertMatchesContract($this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts', ['kind' => $kind]), 'admin.outreach.drafts.create', 201);
        $message = OutreachMessage::query()->where('outreach_target_id', $target->id)->where('kind', $kind)->where('status', 'draft')->firstOrFail();

        return $this->postJson('/api/admin/outreach/messages/'.$message->sqid().'/send');
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function webhook(array $events): TestResponse
    {
        $body = (string) json_encode(['events' => $events]);

        return $this->call('POST', '/webhooks/outreach', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STACKAPELLA_SIGNATURE' => Signature::make('secret', $body, now()->getTimestamp())], $body);
    }

    public function test_the_research_list_comes_in_with_each_row_checked_and_its_own_ref(): void
    {
        OutreachSuppression::factory()->create(['email' => 'said-no@choir.example.com']);

        $imported = $this->postJson('/api/admin/outreach/import', ['rows' => [
            self::row(),
            self::row(['name' => 'Choral Directors', 'group' => 'choral', 'contact' => 'https://choral.example.com/form', 'source' => 'https://choral.example.com/contact', 'angle' => 'course']),
            self::row(['name' => 'Unchecked Choir', 'contact' => 'office@unchecked.example.com', 'verified' => false]),
            self::row(['name' => 'No Source Choir', 'source' => null]),
            self::row(['name' => 'Said No Choir', 'contact' => 'said-no@choir.example.com']),
            self::row(['name' => 'Bad Contact', 'contact' => 'call us']),
        ]]);
        $this->assertMatchesContract($imported, 'admin.outreach.import', 200);
        $imported->assertJsonPath('data.added', 3)->assertJsonPath('data.updated', 0);
        $this->assertSame([4, 5, 6], array_column((array) $imported->json('data.skipped'), 'row'));

        $harmony = OutreachTarget::query()->where('name', 'Harmony Society')->sole();
        $this->assertSame(['o-harmony-societ', 'barbershop', 'US', 'ready'], [$harmony->ref, $harmony->group, $harmony->country, $harmony->status]);
        $this->assertSame('https://choral.example.com/form', OutreachTarget::query()->where('name', 'Choral Directors')->sole()->contact_form_url);
        $this->assertSame('to_research', OutreachTarget::query()->where('name', 'Unchecked Choir')->sole()->status);
        // A row can bring what was found about the organization, each specific with its page.
        $this->postJson('/api/admin/outreach/import', ['rows' => [self::row(['research' => ['note' => 'Harmony University runs an educator track.', 'links' => ['https://harmony.example.com/hu']]])]])->assertJsonPath('data.updated', 1);
        $this->getJson('/api/admin/outreach/'.$harmony->sqid())->assertJsonPath('data.research', ['note' => 'Harmony University runs an educator track.', 'links' => ['https://harmony.example.com/hu']]);
        $this->postJson('/api/admin/outreach/import', ['rows' => [self::row(['name' => 'Bad Link Choir', 'research' => ['links' => ['not a link']]])]])->assertJsonPath('data.skipped.0.row', 1);

        // A row can bring the ref its drafts' links already use.
        $this->postJson('/api/admin/outreach/import', ['rows' => [self::row(['name' => 'Named Ref Choir', 'contact' => 'office@named.example.com', 'ref' => 'o-named'])]])->assertJsonPath('data.added', 1);
        $this->assertSame('o-named', OutreachTarget::query()->where('name', 'Named Ref Choir')->sole()->ref);

        // A row can carry its drafts, written ahead: they wait for Hillel's review and Send.
        $this->postJson('/api/admin/outreach/import', ['rows' => [self::row(['draft' => ['subject' => 'For your chapters', 'body' => 'Written ahead.'], 'followUp' => ['subject' => 'Re: For your chapters', 'body' => 'Again.']])]])
            ->assertJsonPath('data.updated', 1);
        $harmony->refresh();
        $this->assertSame('drafted', $harmony->status);
        $this->assertSame(['first' => 'Written ahead.', 'follow_up' => 'Again.'], $harmony->messages()->pluck('body', 'kind')->all());
        Mail::assertNothingSent();

        $list = $this->getJson('/api/admin/outreach?group=barbershop');
        $this->assertMatchesContract($list, 'admin.outreach.list', 200);
        $list->assertJsonCount(3, 'data')->assertJsonPath('summary.cap', 5)->assertJsonPath('summary.configured', true);

        // Only the admin: an editor can't see or write to them.
        $this->actingAsEditor();
        $this->getJson('/api/admin/outreach')->assertForbidden();
    }

    public function test_one_plain_email_goes_per_press_within_the_cap_and_never_to_a_no(): void
    {
        $target = $this->target();
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts')->assertCreated();
        $draft = OutreachMessage::query()->sole();
        // The draft is Hillel's own words (2026-11-07), a few features for the group, our main page and nothing on top.
        $this->assertSame('Would you take a look at my singing app?', $draft->subject);
        $this->assertStringStartsWith("Hi,\n\nMy name is Hillel. I'm a singer and programmer", $draft->body);
        $this->assertStringContainsString('The courses are all free', $draft->body);
        // A choir hears the learning tracks offer (Hillel, 2026-11-08), before the courses line.
        $this->assertStringContainsString("If you send me a song your choir is rehearsing, I'll make part-by-part learning tracks for your singers, free", $draft->body);
        $this->assertLessThan(strpos($draft->body, 'The courses are all free'), strpos($draft->body, 'If you send me a song'));
        $this->assertStringContainsString("\n\n".Outreach::link()."\n\nCheers,\nHillel", $draft->body);
        $this->assertStringEndsWith('Hillel', $draft->body);
        $this->assertStringNotContainsString('ref=', $draft->body);
        $this->assertStringNotContainsString('no thanks', $draft->body);
        $this->assertSame('drafted', $target->refresh()->status);

        // A draft thrown away, and made again.
        $this->assertMatchesContract($this->deleteJson('/api/admin/outreach/messages/'.$draft->sqid()), 'admin.outreach.drafts.delete', 204);
        $this->assertSame('ready', $target->refresh()->status);
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts')->assertCreated();
        $draft = OutreachMessage::query()->sole();
        $edited = $this->patchJson('/api/admin/outreach/messages/'.$draft->sqid(), ['body' => "Hello,\n\nMy own words.\n\nHillel"]);
        $this->assertMatchesContract($edited, 'admin.outreach.drafts.update', 200);

        $sent = $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send');
        $this->assertMatchesContract($sent, 'admin.outreach.drafts.send', 200);
        $sent->assertJsonPath('data.status', 'sent')->assertJsonPath('data.messages.0.status', 'sent');
        Mail::assertSent(OutreachEmail::class, fn (OutreachEmail $mail): bool => $mail->hasTo('outreach@harmony.example.com') && $mail->text === "Hello,\n\nMy own words.\n\nHillel" && $mail->content()->html === null && $mail->content()->view === null);
        Mail::assertSentCount(1);
        $this->assertSame(1, AuditLog::query()->where('action', 'outreach.sent')->count());

        // Sent once: no second first email, and a sent one can't be edited.
        $this->assertMatchesContract($this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send'), 'admin.outreach.drafts.send', 409);
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts')->assertStatus(409)->assertJsonPath('code', 'outreach_already_sent');
        $this->patchJson('/api/admin/outreach/messages/'.$draft->sqid(), ['body' => 'x'])->assertStatus(409)->assertJsonPath('code', 'outreach_not_a_draft');

        // The cap counts today's sends, by email or by form.
        config(['outreach.daily_cap' => 2]);
        $form = $this->target(['name' => 'Form Choir', 'contact' => 'https://form.example.com/contact']);
        $this->postJson('/api/admin/outreach/'.$form->sqid().'/drafts')->assertCreated();
        $marked = $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('outreach_target_id', $form->id)->sole()->sqid().'/mark-sent');
        $this->assertMatchesContract($marked, 'admin.outreach.drafts.sent', 200);
        $marked->assertJsonPath('data.messages.0.sentVia', 'form');
        $this->draftAndSend($this->target(['name' => 'Third Choir', 'contact' => 'office@third.example.com']))->assertStatus(409)->assertJsonPath('code', 'outreach_daily_cap');
        $this->travel(1)->days();
        $this->actingAsAdmin($this->admin);
        $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('status', 'draft')->sole()->sqid().'/send')->assertOk();

        // Never to an address on the list, or an organization that said no.
        $no = $this->target(['name' => 'No Choir', 'contact' => 'office@no.example.com']);
        $this->assertMatchesContract($this->postJson('/api/admin/outreach/suppressions', ['email' => 'office@no.example.com', 'reason' => 'no_thanks']), 'admin.outreach.suppressions.create', 204);
        $this->assertMatchesContract($this->postJson('/api/admin/outreach/suppressions', ['email' => 'not an address']), 'admin.outreach.suppressions.create', 422);
        $this->postJson('/api/admin/outreach/'.$no->sqid().'/drafts')->assertStatus(409)->assertJsonPath('code', 'outreach_suppressed');
        $this->assertSame('do_not_contact', $no->refresh()->status);

        // Off until its sender is set.
        config(['outreach.token' => '']);
        $off = $this->target(['name' => 'Off Choir', 'contact' => 'office@off.example.com']);
        $this->draftAndSend($off)->assertStatus(409)->assertJsonPath('code', 'outreach_off');
    }

    public function test_one_follow_up_at_most_and_only_after_ten_days_without_a_reply(): void
    {
        $target = $this->target();
        $this->draftAndSend($target)->assertOk();

        $this->travel(9)->days();
        $this->actingAsAdmin($this->admin);
        $this->draftAndSend($target, 'follow_up')->assertStatus(409)->assertJsonPath('code', 'outreach_follow_up_not_due');
        $this->getJson('/api/admin/outreach?status=follow_up_due')->assertJsonCount(0, 'data');

        $this->travel(1)->days();
        $this->actingAsAdmin($this->admin);
        $this->getJson('/api/admin/outreach?status=follow_up_due')->assertJsonCount(1, 'data')->assertJsonPath('summary.followUpsDue', 1);
        $this->getJson('/api/admin/overview')->assertJsonPath('data.outreach.followUpsDue', 1);
        $follow = OutreachMessage::query()->where('kind', 'follow_up')->sole();
        $this->assertStringStartsWith('Re: ', $follow->subject);
        $this->assertSame('Re: Would you take a look at my singing app?', $follow->subject);
        // A choir's follow-up adds the learning tracks offer; every other angle's is the plain one.
        $this->assertSame("Hi again, just checking this didn't get buried. One more thought: if you send me a song your choir is rehearsing, I'll make part-by-part learning tracks for your singers, free (it needs to be public domain or your own arrangement). No worries if it's not for you.\n\n".Outreach::link()."\n\nHillel", $follow->body);
        $this->postJson('/api/admin/outreach/messages/'.$follow->sqid().'/send')->assertOk();
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts', ['kind' => 'follow_up'])->assertStatus(409)->assertJsonPath('code', 'outreach_already_sent');

        // A reply ends the follow-up for another organization.
        $other = $this->target(['name' => 'Replied Choir', 'contact' => 'office@replied.example.com']);
        $this->draftAndSend($other)->assertOk();
        $logged = $this->postJson('/api/admin/outreach/'.$other->sqid().'/replies', ['body' => 'Tell me more, please.']);
        $this->assertMatchesContract($logged, 'admin.outreach.replies.create', 201);
        $logged->assertJsonPath('data.status', 'replied');
        $this->travel(11)->days();
        $this->actingAsAdmin($this->admin);
        $this->draftAndSend($other, 'follow_up')->assertStatus(409)->assertJsonPath('code', 'outreach_follow_up_not_due');
    }

    public function test_deliveries_bounces_and_replies_come_back_and_a_no_is_forever(): void
    {
        $target = $this->target();
        $this->draftAndSend($target)->assertOk();
        $message = OutreachMessage::query()->sole();

        $this->webhook([['type' => 'delivered', 'recipient' => 'outreach@harmony.example.com']])->assertOk();
        $this->assertSame('delivered', $message->refresh()->status);

        // The reply's text, matched by its address; then one saying no.
        $this->webhook([['type' => 'reply', 'from' => 'Outreach@Harmony.example.com', 'subject' => 'Re: Learning parts', 'text' => 'Interesting! Can we talk on Friday?']])->assertOk();
        $this->assertSame(['replied', 1], [$target->refresh()->status, OutreachReply::query()->where('outreach_target_id', $target->id)->count()]);
        $this->getJson('/api/admin/overview')->assertJsonPath('data.outreach.unreadReplies', 1);
        $this->webhook([['type' => 'reply', 'from' => 'outreach@harmony.example.com', 'text' => "No thanks.\n\nSent from my phone"]])->assertOk();
        $target->refresh();
        $this->assertSame(['do_not_contact', 'declined'], [$target->status, $target->outcome]);
        $this->assertTrue(OutreachSuppression::blocks('outreach@harmony.example.com'));

        // A hard bounce: the address is dead for every email we send.
        $bounced = $this->target(['name' => 'Bounced Choir', 'contact' => 'old@bounced.example.com']);
        $this->draftAndSend($bounced)->assertOk();
        $this->webhook([['type' => 'bounce', 'recipient' => 'old@bounced.example.com', 'bounceType' => 'hard', 'detail' => '550 no such user']])->assertOk();
        $this->assertSame('do_not_contact', $bounced->refresh()->status);
        $this->assertTrue(EmailSuppression::isSuppressed('old@bounced.example.com'));
        $this->assertSame('550 no such user', OutreachMessage::query()->where('outreach_target_id', $bounced->id)->sole()->bounce_note);

        // From nobody we wrote to: kept unfiled until Hillel files it.
        $this->webhook([['type' => 'reply', 'from' => 'someone@elsewhere.example.org', 'text' => 'Who is this?']])->assertOk();
        $list = $this->getJson('/api/admin/outreach')->assertJsonPath('summary.unfiledReplies', 1);
        $filed = $this->postJson('/api/admin/outreach/replies/'.$list->json('unfiled.0.id').'/read', ['targetId' => $bounced->sqid()]);
        $this->assertMatchesContract($filed, 'admin.outreach.replies.read', 200);
        $filed->assertJsonPath('data.targetId', $bounced->sqid());

        // Unsigned: refused; no secret: not there.
        $this->postJson('/webhooks/outreach', ['events' => []])->assertStatus(400);
        config(['outreach.webhook_secret' => '']);
        $this->webhook([])->assertNotFound();
    }

    public function test_only_a_reply_from_the_address_itself_can_stop_an_organization_and_the_log_keeps_no_address(): void
    {
        $target = $this->target();
        $this->draftAndSend($target)->assertOk();

        // Someone else at the same domain says no: filed under the organization, nothing else.
        $this->webhook([['type' => 'reply', 'from' => 'prankster@harmony.example.com', 'text' => 'Unsubscribe']])->assertOk();
        $target->refresh();
        $this->assertSame(['sent', null], [$target->status, $target->outcome]);
        $this->assertFalse(OutreachSuppression::blocks('prankster@harmony.example.com'));
        $this->assertSame($target->id, OutreachReply::query()->where('from_email', 'prankster@harmony.example.com')->sole()->outreach_target_id);

        // A free-mail domain is never matched, even with one organization there.
        config(['outreach.free_mail_domains' => ['freemail.example.com']]);
        $free = $this->target(['name' => 'Free Mail Choir', 'contact' => 'director@freemail.example.com']);
        $this->webhook([['type' => 'reply', 'from' => 'stranger@freemail.example.com', 'text' => 'Stop']])->assertOk();
        $this->assertNull(OutreachReply::query()->where('from_email', 'stranger@freemail.example.com')->sole()->outreach_target_id);
        $this->assertSame('ready', $free->refresh()->status);

        // Not an address, or longer than we keep: dropped.
        $this->webhook([['type' => 'reply', 'from' => 'not an address', 'text' => 'Hi'], ['type' => 'reply', 'from' => str_repeat('a', 180).'@harmony.example.com', 'text' => 'Hi']])->assertOk();
        $this->assertSame(2, OutreachReply::query()->count());

        // The address itself: the no is kept, and the audit log has only fingerprints.
        $this->webhook([['type' => 'reply', 'from' => 'outreach@harmony.example.com', 'text' => 'No thanks']])->assertOk();
        $this->assertSame('do_not_contact', $target->refresh()->status);
        $this->patchJson('/api/admin/outreach/'.$free->sqid(), ['contact' => 'new@choir.example.com', 'source' => 'https://choir.example.com/contact'])->assertOk();
        $logged = (string) json_encode(AuditLog::query()->whereIn('action', ['outreach.reply', 'outreach.target_changed'])->get(['before', 'after']));
        $this->assertStringNotContainsString('@', $logged);
    }

    public function test_a_replayed_webhook_is_taken_once(): void
    {
        $target = $this->target();
        $this->draftAndSend($target)->assertOk();
        $body = (string) json_encode(['events' => [['type' => 'reply', 'from' => 'outreach@harmony.example.com', 'text' => 'Tell me more']]]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_STACKAPELLA_SIGNATURE' => Signature::make('secret', $body, now()->getTimestamp())];

        $this->call('POST', '/webhooks/outreach', [], [], [], $headers, $body)->assertOk()->assertSeeText('Took 1');
        $this->call('POST', '/webhooks/outreach', [], [], [], $headers, $body)->assertOk()->assertSeeText('Took 0');
        $this->assertSame(1, OutreachReply::query()->count());
    }

    public function test_a_test_goes_only_to_the_admin_and_counts_for_nothing(): void
    {
        $target = $this->target();
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts')->assertCreated();
        $draft = OutreachMessage::query()->sole();
        config(['outreach.daily_cap' => 1]);

        $tested = $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/test');
        $this->assertMatchesContract($tested, 'admin.outreach.drafts.test', 200);
        $tested->assertJsonPath('data.sentTo', $this->admin->email);
        Mail::assertSent(OutreachEmail::class, fn (OutreachEmail $mail): bool => $mail->hasTo((string) $this->admin->email) && ! $mail->hasTo('outreach@harmony.example.com') && str_starts_with($mail->mailSubject, '[Test] ') && $mail->text === $draft->body);
        // Nothing counted, nothing marked: the one real send of the day still goes.
        $this->assertSame(['draft', 'drafted'], [$draft->refresh()->status, $target->refresh()->status]);
        $this->assertSame(1, AuditLog::query()->where('action', 'outreach.test_sent')->count());
        $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send')->assertOk();

        config(['outreach.token' => '']);
        $this->assertMatchesContract($this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/test'), 'admin.outreach.drafts.test', 409);
    }

    public function test_a_time_sensitive_email_shows_as_near_warns_when_late_and_its_follow_up_waits(): void
    {
        $target = $this->target(['sendBy' => '2026-11-12', 'followUpNotBefore' => '2026-11-25']);
        $this->getJson('/api/admin/outreach/'.$target->sqid())->assertJsonPath('data.sendBy', '2026-11-12')->assertJsonPath('data.sendBySoon', true)->assertJsonPath('data.pastSendBy', false);
        $this->getJson('/api/admin/outreach')->assertJsonPath('summary.sendBySoon', 1);
        $this->getJson('/api/admin/overview')->assertJsonPath('data.outreach.sendBySoon', 1);

        // Past its day: Send asks again (the wording may be stale), then goes.
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00', 'UTC'));
        $this->actingAsAdmin($this->admin);
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts')->assertCreated();
        $draft = OutreachMessage::query()->sole();
        $late = $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send');
        $this->assertMatchesContract($late, 'admin.outreach.drafts.send', 409);
        $late->assertJsonPath('code', 'outreach_past_send_by');
        $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send', ['staleOk' => true])->assertOk();
        $this->getJson('/api/admin/outreach')->assertJsonPath('summary.sendBySoon', 0);

        // Ten days on is the 23rd; its follow-up waits for the 25th.
        $this->travelTo(Carbon::parse('2026-11-24 09:00:00', 'UTC'));
        $this->actingAsAdmin($this->admin);
        $this->draftAndSend($target, 'follow_up')->assertStatus(409)->assertJsonPath('code', 'outreach_follow_up_not_due');
        $this->getJson('/api/admin/outreach')->assertJsonPath('summary.followUpsDue', 0);
        $this->travelTo(Carbon::parse('2026-11-25 09:00:00', 'UTC'));
        $this->actingAsAdmin($this->admin);
        $this->getJson('/api/admin/outreach')->assertJsonPath('summary.followUpsDue', 1);
        $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('kind', 'follow_up')->sole()->sqid().'/send')->assertOk();
    }

    public function test_an_email_held_to_a_later_day_asks_again_and_a_fact_check_reaches_its_draft(): void
    {
        $target = $this->target(['sendNotBefore' => '2026-11-20', 'timingNote' => 'Check their concert date on the day.']);
        $this->getJson('/api/admin/outreach/'.$target->sqid())->assertJsonPath('data.tooEarly', true)->assertJsonPath('data.timingNote', 'Check their concert date on the day.');
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts')->assertCreated();
        $draft = OutreachMessage::query()->sole();

        // A fact check's fixes reach a draft not sent yet, by importing again.
        $this->postJson('/api/admin/outreach/import', ['rows' => [self::row(['draft' => ['subject' => 'Fixed subject', 'body' => 'Fixed words.']])]])->assertJsonPath('data.updated', 1);
        $draft = OutreachMessage::query()->sole();
        $this->assertSame(['Fixed words.', 'drafted'], [$draft->body, $target->refresh()->status]);

        $early = $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send');
        $this->assertMatchesContract($early, 'admin.outreach.drafts.send', 409);
        $early->assertJsonPath('code', 'outreach_too_early');
        $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send', ['earlyOk' => true])->assertOk();

        // Once emailed, a re-import leaves it as it is.
        $again = $this->postJson('/api/admin/outreach/import', ['rows' => [self::row(['draft' => ['subject' => 'Later', 'body' => 'Later words.']])]]);
        $again->assertJsonPath('data.updated', 0)->assertJsonPath('data.skipped.0.reason', __('outreach.import.in_progress'));
    }

    public function test_an_emails_links_go_exactly_as_written_with_no_tag_added(): void
    {
        config(['counts.enabled' => true]);
        // Imported with the research's slug in a link, and a slug too long for a tag (Hillel, 2026-11-05: "Make the URL just our domain, no extra tracking").
        $words = "See https://stackapella.com/courses/barbershop?ref=bhs.\nAnd https://www.stackapella.com/ (the site), or https://example.org/x.";
        $this->postJson('/api/admin/outreach/import', ['rows' => [self::row(['name' => 'Barbershop Harmony Society', 'ref' => 'o-barbershop-harmony-society-long', 'draft' => ['subject' => 'Hi', 'body' => $words]])]])->assertJsonPath('data.added', 1);
        $target = OutreachTarget::query()->sole();
        $this->assertMatchesRegularExpression('/^o-[a-z0-9-]{1,14}$/', $target->ref);
        $draft = OutreachMessage::query()->sole();
        $this->assertSame($words, $draft->body, 'imported as written');

        // Shown, edited, tested and sent as written.
        $this->getJson('/api/admin/outreach/'.$target->sqid())->assertJsonPath('data.messages.0.body', $words)->assertJsonPath('data.link', Outreach::link());
        $this->patchJson('/api/admin/outreach/messages/'.$draft->sqid(), ['body' => "Hi,\n\nhttps://stackapella.com\n\nHillel"])->assertOk();
        $this->assertSame("Hi,\n\nhttps://stackapella.com\n\nHillel", $draft->refresh()->body);
        $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/test')->assertOk();
        $this->postJson('/api/admin/outreach/messages/'.$draft->sqid().'/send')->assertOk();
        Mail::assertSent(OutreachEmail::class, fn (OutreachEmail $mail): bool => $mail->text === "Hi,\n\nhttps://stackapella.com\n\nHillel");
        Mail::assertNotSent(OutreachEmail::class, fn (OutreachEmail $mail): bool => str_contains($mail->text, 'ref='));
        $this->assertSame("Hi,\n\nhttps://stackapella.com\n\nHillel", $draft->refresh()->body);
        $this->assertMatchesRegularExpression('#^https://[a-z.]+$#', Outreach::link(), 'our domain, no path and no ?ref=');

        // The one test the counts and the site's "Here from Hillel's email?" share.
        $this->assertTrue(Outreach::known($target->ref));
        $this->assertTrue(Outreach::known(strtoupper($target->ref)));
        $this->assertFalse(Outreach::known('o-nobody'));
        $this->assertFalse(Outreach::known(null));

        // Someone following it is counted as this organization's.
        $this->postJson('/api/counts', ['events' => [['kind' => 'f_arrive']], 'signedIn' => false, 'source' => $target->ref])->assertNoContent();
        $this->assertSame([$target->ref], DB::table('usage_counts')->where('kind', 'f_arrive')->pluck('source')->all());
    }

    public function test_results_come_from_the_anonymous_counts_by_organization_group_and_angle(): void
    {
        config(['counts.enabled' => true]);
        $big = $this->target();
        $small = $this->target(['name' => 'Small Choir', 'group' => 'church', 'contact' => 'office@small.example.com', 'angle' => 'school']);
        $this->draftAndSend($big)->assertOk();
        $this->postJson('/api/admin/outreach/'.$big->sqid().'/replies', ['body' => 'Yes, interested.'])->assertCreated();
        $this->assertMatchesContract($this->patchJson('/api/admin/outreach/'.$big->sqid(), ['outcome' => 'interested', 'notes' => 'Call on Friday.']), 'admin.outreach.update', 200);
        $this->assertMatchesContract($this->patchJson('/api/admin/outreach/'.$big->sqid(), ['outcome' => 'maybe']), 'admin.outreach.update', 422);
        $this->assertMatchesContract($this->getJson('/api/admin/outreach/nope'), 'admin.outreach.show', 404);

        // The counts take each organization's own tag, and no other o- tag.
        $this->postJson('/api/counts', ['events' => [['kind' => 'f_arrive'], ['kind' => 'school_redeemed']], 'signedIn' => false, 'source' => $big->ref])->assertNoContent();
        $this->postJson('/api/counts', ['events' => [['kind' => 'f_arrive']], 'signedIn' => false, 'source' => 'o-made-up'])->assertNoContent();
        $this->assertEqualsCanonicalizing([$big->ref, 'other'], DB::table('usage_counts')->where('kind', 'f_arrive')->pluck('source')->all());
        $this->assertSame([$big->ref], DB::table('usage_counts')->where('kind', 'school_redeemed')->pluck('source')->all());
        // More days of them: six arrivals in all from the big one's link, three from the small one's.
        $day = static fn (string $kind, string $source, int $count): array => ['day' => '2026-11-09', 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $count];
        DB::table('usage_counts')->insert([$day('f_arrive', $big->ref, 5), $day('f_account', $big->ref, 2), $day('f_arrive', $small->ref, 3)]);

        $show = $this->getJson('/api/admin/outreach/'.$big->sqid());
        $this->assertMatchesContract($show, 'admin.outreach.show', 200);
        // Its email went to the plain domain: nothing can be counted for it, so it reads "not tracked".
        $show->assertJsonPath('data.results', ['arrivals' => 6, 'lesson1' => 0, 'signedUp' => 2, 'schoolCodes' => 1, 'few' => false, 'tracked' => false]);
        // An email sent before the plain links carried the organization's tag: counted.
        OutreachMessage::query()->where('outreach_target_id', $big->id)->update(['body' => 'Old words: https://stackapella.com/for/choir-directors?ref='.$big->ref]);
        $this->getJson('/api/admin/outreach/'.$big->sqid())->assertJsonPath('data.results.tracked', true);
        // Under five arrivals, folded: a handful never stands for a person.
        $this->getJson('/api/admin/outreach/'.$small->sqid())->assertJsonPath('data.results', ['arrivals' => null, 'lesson1' => null, 'signedUp' => null, 'schoolCodes' => null, 'few' => true, 'tracked' => false]);

        $results = $this->getJson('/api/admin/outreach/results');
        $this->assertMatchesContract($results, 'admin.outreach.results', 200);
        $barbershop = collect((array) $results->json('data.byGroup'))->firstWhere('key', 'barbershop');
        $this->assertSame([1, 1, 1, 1, 1.0, 1, 6, 2], [$barbershop['organizations'], $barbershop['emailed'], $barbershop['replied'], $barbershop['positive'], (float) $barbershop['responseRate'], $barbershop['tracked'], $barbershop['arrivals'], $barbershop['signedUp']]);
        // An organization emailed since the plain links: emailed, and none of it tracked.
        $new = $this->target(['name' => 'New Choir', 'group' => 'community', 'contact' => 'office@new.example.com']);
        $this->draftAndSend($new)->assertOk();
        $community = collect((array) $this->getJson('/api/admin/outreach/results')->json('data.byGroup'))->firstWhere('key', 'community');
        $this->assertSame([1, 0], [$community['emailed'], $community['tracked']]);
        $this->assertNull(collect((array) $results->json('data.byAngle'))->firstWhere('key', 'school')['responseRate']);

        // An editor's overview has no outreach.
        $this->actingAsEditor();
        $this->getJson('/api/admin/overview')->assertJsonPath('data.outreach', null);
    }
}
