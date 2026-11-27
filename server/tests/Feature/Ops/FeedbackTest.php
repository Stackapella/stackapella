<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Beta\ForgetSinger;
use App\Models\BetaSinger;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\Feedback\DeviceDetails;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Contact us, made easier (docs/feedback-plan.md): only the message is needed, a guest may leave no email (behind
 * feedback2), "Don't reply to me", where it was opened from, the device as a kind and a browser family, Maybe spam,
 * the guest's daily limit, beta notes in the same inbox, and the saved replies in "we".
 */
final class FeedbackTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function open(): void
    {
        app(Flags::class)->set('feedback2', 'on', null, 'test');
    }

    private function sent(string $kind): int
    {
        return (int) DB::table('usage_counts')->where('kind', $kind)->sum('count');
    }

    public function test_the_switch_is_served_and_off_a_guest_still_needs_an_email(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('data.feedback2', false);
        $this->assertMatchesContract($this->postJson('/api/support', ['message' => 'The alto is wrong in bar 12.']), 'support.create', 422);

        // Off, "Don't reply to me" is today's form: the address stays.
        $ana = User::factory()->create(['email' => 'ana@example.com']);
        $this->actingAs($ana)->postJson('/api/support', ['message' => 'Hello there.', 'reply' => false])->assertCreated();
        $this->assertSame('ana@example.com', SupportMessage::query()->latest('id')->value('email'));

        $this->open();
        $this->getJson('/api/auth/config')->assertJsonPath('data.feedback2', true);
    }

    public function test_a_guest_sends_only_a_message_and_admin_sees_no_reply_address(): void
    {
        $this->open();

        $sent = $this->postJson('/api/support', ['message' => 'The alto is wrong in bar 12.', 'moment' => 'site_404', 'context' => ['page' => '/songs/amazing-grace/alto']]);

        $this->assertMatchesContract($sent, 'support.create', 201);
        $message = SupportMessage::query()->sole();
        $this->assertSame('other', $message->category);
        $this->assertSame('site_404', $message->moment);
        $this->assertNull($message->email);
        $this->assertSame(1, $this->sent('fb_sent:none'));
        $this->assertSame(0, $this->sent('fb_sent:other'));

        $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/support');
        $this->assertMatchesContract($list, 'admin.support.list', 200);
        $list->assertJsonPath('data.0.canReply', false)->assertJsonPath('data.0.moment', 'site_404')->assertJsonPath('data.0.maybeSpam', false);
        $this->assertMatchesContract($this->getJson('/api/admin/support/'.$message->sqid()), 'admin.support.show', 200);

        // Answered anyway (a note kept), and nothing emailed.
        $this->postJson('/api/admin/support/'.$message->sqid().'/reply', ['body' => 'Thanks, fixed.'])->assertOk();
        Mail::assertNothingQueued();
    }

    public function test_an_account_uses_its_own_address_unless_it_says_dont_reply(): void
    {
        $this->open();
        $ana = User::factory()->create(['email' => 'ana@example.com']);

        $this->actingAs($ana)->postJson('/api/support', ['category' => 'idea', 'message' => 'A metronome, please.', 'email' => 'other@example.com'])->assertCreated();
        $this->assertSame('ana@example.com', SupportMessage::query()->latest('id')->value('email'));
        $this->assertSame(1, $this->sent('fb_sent:idea'));

        $this->actingAs($ana)->postJson('/api/support', ['category' => 'idea', 'message' => 'And a drone.', 'reply' => false])->assertCreated();
        $quiet = SupportMessage::query()->latest('id')->firstOrFail();
        $this->assertNull($quiet->email);
        // Still theirs, so staff can see the song asked about.
        $this->assertSame($ana->id, $quiet->user_id);

        // A guest's "Don't reply to me" drops the address typed too.
        auth()->forgetGuards();
        $this->app['auth']->guard('web')->logout();
        $this->postJson('/api/support', ['message' => 'Lovely app.', 'email' => 'kim@example.com', 'reply' => false])->assertCreated();
        $this->assertNull(SupportMessage::query()->latest('id')->value('email'));
    }

    public function test_the_device_is_kept_as_a_kind_and_a_family_and_the_moments_context_is_kept(): void
    {
        $this->open();

        $this->postJson('/api/support', ['category' => 'recording', 'message' => 'The choir is out of time.', 'moment' => 'choir_ask', 'context' => [
            'appVersion' => '2026.11.27', 'device' => self::IPHONE, 'browser' => self::IPHONE, 'language' => 'es-MX', 'skin' => 'game',
            'templateId' => 'amazing-grace', 'partKey' => 'alto', 'choirRequestId' => 'Cr8tYp', 'errorCode' => 'a1b2c3d4', 'choirIssue' => 'timing',
        ]])->assertCreated();

        $context = (array) SupportMessage::query()->sole()->context;
        $this->assertSame('phone', $context['device']);
        $this->assertSame('safari', $context['browser']);
        $this->assertEqualsCanonicalizing(['appVersion', 'device', 'browser', 'language', 'skin', 'templateId', 'partKey', 'choirRequestId', 'errorCode', 'choirIssue'], array_keys($context));
        $this->assertStringNotContainsString('Mozilla', (string) json_encode($context));

        // What the app sends now is kept as it is; a moment or a chip off the list is refused.
        $this->postJson('/api/support', ['message' => 'Hi there.', 'context' => ['device' => 'tablet', 'browser' => 'firefox']])->assertCreated();
        $this->assertEquals(['device' => 'tablet', 'browser' => 'firefox'], SupportMessage::query()->latest('id')->firstOrFail()->context);
        $this->postJson('/api/support', ['message' => 'Hi there.', 'moment' => 'home'])->assertStatus(422);
        $this->postJson('/api/support', ['message' => 'Hi there.', 'context' => ['choirIssue' => 'loud']])->assertStatus(422);
    }

    public function test_device_details_never_keep_a_user_agent(): void
    {
        $cases = [
            [self::IPHONE, 'phone', 'safari'],
            ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/604.1', 'tablet', 'chrome'],
            ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/24.0 Chrome/117.0 Mobile Safari/537.36', 'phone', 'samsung'],
            ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', 'tablet', 'chrome'],
            ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0', 'computer', 'edge'],
            ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14.1; rv:121.0) Gecko/20100101 Firefox/121.0', 'computer', 'firefox'],
            ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 OPR/105.0', 'computer', 'opera'],
            ['iPhone', 'phone', 'other'],
        ];

        foreach ($cases as [$agent, $kind, $family]) {
            $this->assertSame($kind, DeviceDetails::kind($agent), $agent);
            $this->assertSame($family, DeviceDetails::family($agent), $agent);
        }

        $this->assertNull(DeviceDetails::kind(''));
        $this->assertNull(DeviceDetails::reduce(null));
        $this->assertSame(['page' => '/sing'], DeviceDetails::reduce(['page' => '/sing']));
    }

    public function test_a_guests_message_with_links_while_turnstile_is_off_is_maybe_spam_and_still_listed(): void
    {
        $this->open();
        $ana = User::factory()->create();

        $this->postJson('/api/support', ['message' => 'Cheap pills https://spam.example.com and www.spam.example.com'])->assertCreated();
        $this->postJson('/api/support', ['message' => 'One link: https://example.com/song'])->assertCreated();
        $this->actingAs($ana)->postJson('/api/support', ['message' => 'Two links https://example.com/a https://example.com/b'])->assertCreated();

        $this->assertSame([true, false, false], SupportMessage::query()->orderBy('id')->pluck('maybe_spam')->all());

        $this->actingAsAdmin();
        $this->assertCount(3, $this->getJson('/api/admin/support')->json('data'));
        $spam = $this->getJson('/api/admin/support?maybeSpam=1');
        $this->assertMatchesContract($spam, 'admin.support.list', 200);
        $spam->assertJsonCount(1, 'data')->assertJsonPath('data.0.maybeSpam', true);
    }

    public function test_with_turnstile_on_a_guests_links_are_not_marked(): void
    {
        $this->open();
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret' => 'secret']);
        app(Flags::class)->set('shield_mode', 'on', null, 'A flood');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->postJson('/api/support', ['message' => 'Two links https://example.com/a https://example.com/b', 'turnstileToken' => 'ok'])->assertCreated();

        $this->assertFalse(SupportMessage::query()->sole()->maybe_spam);
    }

    public function test_a_guest_sends_at_most_20_a_day_as_well_as_5_an_hour(): void
    {
        $this->open();

        for ($hour = 0; $hour < 4; $hour++) {
            for ($i = 0; $i < 5; $i++) {
                $this->postJson('/api/support', ['message' => "Note {$hour}.{$i}"])->assertCreated();
            }

            $this->postJson('/api/support', ['message' => 'One too many this hour'])->assertStatus(429);
            $this->travel(61)->minutes();
        }

        // A new hour, but 20 today already.
        $this->assertMatchesContract($this->postJson('/api/support', ['message' => 'The 21st']), 'support.create', 429);
        $this->assertSame(20, SupportMessage::query()->count());
    }

    public function test_a_beta_note_lands_in_support_to_be_answered_and_goes_when_the_singer_is_forgotten(): void
    {
        $singer = BetaSinger::factory()->create();

        $sent = $this->asSinger($singer)->postJson('/api/beta/feedback', ['text' => 'The alto comes in early at verse 2', 'templateId' => 'amazing-grace', 'partKey' => 'alto', 'beat' => 33.5]);

        $this->assertMatchesContract($sent, 'beta.feedback.create', 201);
        $note = SupportMessage::query()->sole();
        $this->assertSame($sent->json('data.id'), $note->sqid());
        $this->assertSame(SupportMessage::BETA, $note->category);
        // Equal in any order: MySQL keeps a JSON object's keys its own way.
        $this->assertEquals(['templateId' => 'amazing-grace', 'partKey' => 'alto', 'beat' => 33.5, 'betaSinger' => $singer->sqid()], $note->context);
        $this->assertNotNull($note->email);
        $this->assertSame(1, $this->sent('fb_sent:beta'));

        $this->actingAsAdmin();
        $beta = $this->getJson('/api/admin/support?category=beta');
        $this->assertMatchesContract($beta, 'admin.support.list', 200);
        $beta->assertJsonCount(1, 'data')->assertJsonPath('data.0.canReply', true);

        app(ForgetSinger::class)->handle($singer->refresh(), null);
        $this->assertSame(0, SupportMessage::query()->count());
    }

    public function test_messages_kept_before_lose_their_user_agent_and_the_migration_runs_twice(): void
    {
        $old = SupportMessage::factory()->create(['context' => ['appVersion' => '2026.11.1', 'device' => self::IPHONE, 'browser' => self::IPHONE, 'page' => '/sing']]);
        $plain = SupportMessage::factory()->create(['context' => null]);
        $migration = require database_path('migrations/2026_11_27_100000_add_moment_and_spam_to_support_messages.php');

        $migration->up();
        $migration->up();

        $this->assertEquals(['appVersion' => '2026.11.1', 'device' => 'phone', 'browser' => 'safari', 'page' => '/sing'], $old->refresh()->context);
        $this->assertNull($plain->refresh()->context);
        $this->assertFalse($old->maybe_spam);
        $this->assertNull($old->moment);
    }

    public function test_the_saved_replies_speak_as_we(): void
    {
        foreach (['en', 'es'] as $locale) {
            foreach ((array) trans('support.saved', [], 'en') as $key => $reply) {
                $body = (string) __('support.saved.'.$key.'.body', [], $locale);

                $this->assertDoesNotMatchRegularExpression("/\\bI('ll|'ve|'d|'m)?\\b|\\bme\\b|\\bmy\\b/", $body, "{$locale} {$key}");
                $this->assertStringNotContainsString('Hillel', $body);
            }
        }

        $this->assertStringNotContainsString('reviso', (string) __('support.saved.silent_mic.body', [], 'es'));
        $this->assertStringContainsString("We'd love to make learning tracks", (string) __('support.saved.tracks_score.body'));
    }
}
