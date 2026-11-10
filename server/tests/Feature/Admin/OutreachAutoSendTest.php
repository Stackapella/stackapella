<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\Outreach\OutreachEmail;
use App\Models\AuditLog;
use App\Models\OutreachMessage;
use App\Models\OutreachTarget;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Hillel's outreach_autosend switch (2026-11-05: "can you auto send 5 a day"): one drafted first email
 * a scheduled turn, weekday mornings from 9:00 to noon in the report's zone, until the day's cap of
 * first emails with his own first sends counted, his follow-ups never (2026-11-09, "5 new +
 * follow-ups"); soonest send-by day first; every rule of Send kept; never a follow-up; off
 * until he switches it on.
 */
final class OutreachAutoSendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Tuesday, 10:00 in the report's zone.
        config(['admin.report_timezone' => 'Europe/London']);
        $this->travelTo(Carbon::parse('2026-11-10 10:00:00', 'Europe/London'));
        config(['outreach.from' => 'hillel@hello.example.com', 'outreach.token' => 'token', 'outreach.account_id' => 'account', 'outreach.daily_cap' => 5]);
        // The failure test needs the real mailer (a faked one never fails).
        if ($this->name() !== 'test_a_failed_send_skips_that_organization_instead_of_retrying') {
            Mail::fake();
        }
        $this->admin = $this->actingAsAdmin();
    }

    /**
     * A drafted organization, ready to be written to, with its first email waiting.
     *
     * @param  array<string, mixed>  $row
     */
    private function drafted(string $name, array $row = []): OutreachTarget
    {
        $slug = strtolower(str_replace(' ', '', $name));
        $this->postJson('/api/admin/outreach', ['name' => $name, 'group' => 'barbershop', 'country' => 'us', 'contact' => "office@{$slug}.example.com",
            'source' => "https://{$slug}.example.com/contact", 'angle' => 'choir_groups', ...$row])->assertCreated();
        $target = OutreachTarget::query()->latest('id')->firstOrFail();
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts')->assertCreated();

        return $target->refresh();
    }

    private function turn(): void
    {
        $this->artisan('app:outreach:autosend')->assertSuccessful();
    }

    private function switchOn(): void
    {
        app(Flags::class)->set('outreach_autosend', 'on', null, 'Hillel: auto send 5 a day');
    }

    private function sentTo(OutreachTarget $target): bool
    {
        return OutreachMessage::query()->where('outreach_target_id', $target->id)->where('kind', OutreachMessage::FIRST)->whereNotNull('sent_at')->exists();
    }

    public function test_nothing_goes_while_the_switch_is_off(): void
    {
        $this->drafted('Harmony Society');
        $this->turn();
        Mail::assertNothingSent();
        $this->assertSame('off', app(Flags::class)->value('outreach_autosend'));
    }

    public function test_one_a_turn_soonest_send_by_first_audited_as_automatic(): void
    {
        $later = $this->drafted('Later Choir', ['sendBy' => '2026-11-30']);
        $none = $this->drafted('Undated Choir');
        $soon = $this->drafted('Soon Choir', ['sendBy' => '2026-11-12']);
        $this->switchOn();

        $this->turn();
        Mail::assertSentCount(1);
        $this->assertTrue($this->sentTo($soon));
        $this->turn();
        $this->assertTrue($this->sentTo($later));
        $this->turn();
        $this->assertTrue($this->sentTo($none), 'no send-by day goes last');
        Mail::assertSentCount(3);
        Mail::assertSent(OutreachEmail::class, fn (OutreachEmail $mail): bool => $mail->hasTo('office@soonchoir.example.com') && ! str_contains($mail->text, 'ref='));

        $audit = AuditLog::query()->where('action', 'outreach.sent')->where('subject_id', $soon->id)->sole();
        $this->assertNull($audit->actor_id);
        $this->assertTrue($audit->after['automatic'] ?? false);
        $this->assertNull(OutreachMessage::query()->where('outreach_target_id', $soon->id)->sole()->sent_by);

        // Nothing waiting: a turn sends nothing, and never twice.
        $this->turn();
        Mail::assertSentCount(3);
    }

    public function test_the_days_cap_counts_hillels_own_sends(): void
    {
        config(['outreach.daily_cap' => 2]);
        $mine = $this->drafted('Mine Choir');
        $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('outreach_target_id', $mine->id)->sole()->sqid().'/send')->assertOk();
        $one = $this->drafted('One Choir');
        $two = $this->drafted('Two Choir');
        $this->switchOn();

        $this->turn();
        $this->turn();
        $this->assertTrue($this->sentTo($one));
        $this->assertFalse($this->sentTo($two), 'the cap of 2 was reached with Hillel\'s own send');
        Mail::assertSentCount(2);

        // Tomorrow morning, the next.
        $this->travelTo(Carbon::parse('2026-11-11 09:00:00', 'Europe/London'));
        $this->turn();
        $this->assertTrue($this->sentTo($two));
    }

    /**
     * Hillel's own first emails, sent 11 days ago so their follow-ups are due today.
     *
     * @return list<OutreachTarget>
     */
    private function firstsSentElevenDaysAgo(int $count): array
    {
        $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'Europe/London'));
        $this->actingAsAdmin($this->admin);
        $targets = [];
        foreach (array_slice(['Alder', 'Birch', 'Cedar', 'Elm', 'Fir'], 0, $count) as $name) {
            $targets[] = $target = $this->drafted("{$name} Chorus");
            $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('outreach_target_id', $target->id)->sole()->sqid().'/send')->assertOk();
        }
        $this->travelTo(Carbon::parse('2026-11-10 10:00:00', 'Europe/London'));
        $this->actingAsAdmin($this->admin);

        return $targets;
    }

    private function sendFollowUp(OutreachTarget $target): TestResponse
    {
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts', ['kind' => 'follow_up'])->assertCreated();

        return $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('outreach_target_id', $target->id)->where('kind', OutreachMessage::FOLLOW_UP)->sole()->sqid().'/send');
    }

    public function test_follow_ups_never_use_up_the_cap_of_new_emails(): void
    {
        // Hillel, 2026-11-09: "I expect to see emails to new people"; his choice, "5 new + follow-ups".
        foreach ($this->firstsSentElevenDaysAgo(5) as $earlier) {
            $this->sendFollowUp($earlier)->assertOk();
        }
        $new = $this->drafted('New Choir');
        $this->getJson('/api/admin/outreach')->assertJsonPath('summary.sentToday', 0)->assertJsonPath('summary.cap', 5);
        $this->switchOn();

        $this->turn();

        $this->assertTrue($this->sentTo($new), 'five follow-ups today left the five new emails untouched');
        $this->getJson('/api/admin/outreach')->assertJsonPath('summary.sentToday', 1);
    }

    public function test_a_sixth_new_email_waits_for_tomorrow_while_a_follow_up_still_goes(): void
    {
        [$earlier] = $this->firstsSentElevenDaysAgo(1);
        foreach (['Lark', 'Robin', 'Swift', 'Wren', 'Finch'] as $name) {
            $target = $this->drafted("{$name} Singers");
            $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('outreach_target_id', $target->id)->sole()->sqid().'/send')->assertOk();
        }
        $sixth = $this->drafted('Sixth Choir');

        $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('outreach_target_id', $sixth->id)->sole()->sqid().'/send')
            ->assertStatus(409)->assertJsonPath('code', 'outreach_daily_cap');
        $this->switchOn();
        $this->turn();
        $this->assertFalse($this->sentTo($sixth));

        $this->sendFollowUp($earlier)->assertOk();
        $this->getJson('/api/admin/outreach')->assertJsonPath('summary.sentToday', 5);
    }

    public function test_only_on_weekday_mornings_in_the_reports_zone(): void
    {
        $target = $this->drafted('Harmony Society');
        $this->switchOn();

        foreach (['2026-11-10 08:59:00', '2026-11-10 12:00:00', '2026-11-14 10:00:00', '2026-11-15 10:00:00'] as $at) {
            $this->travelTo(Carbon::parse($at, 'Europe/London'));
            $this->turn();
        }
        Mail::assertNothingSent();

        // 09:00 in London, whatever the server's clock says.
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00', 'Europe/London')->utc());
        $this->turn();
        $this->assertTrue($this->sentTo($target));
    }

    public function test_every_rule_of_send_holds(): void
    {
        $suppressed = $this->drafted('No Choir', ['sendBy' => '2026-11-11']);
        $this->postJson('/api/admin/outreach/suppressions', ['email' => 'office@nochoir.example.com', 'reason' => 'no_thanks'])->assertNoContent();
        $early = $this->drafted('Early Choir', ['sendBy' => '2026-11-12', 'sendNotBefore' => '2026-11-20']);
        $stale = $this->drafted('Stale Choir', ['sendBy' => '2026-11-09']);
        $form = $this->drafted('Form Choir', ['contact' => 'https://formchoir.example.com/contact', 'sendBy' => '2026-11-13']);
        $ok = $this->drafted('Fine Choir', ['sendBy' => '2026-11-14']);
        $this->switchOn();

        $this->turn();
        Mail::assertSentCount(1);
        $this->assertTrue($this->sentTo($ok), 'the suppressed, the held, the stale and the form-only were passed over');
        foreach ([$suppressed, $early, $stale, $form] as $target) {
            $this->assertFalse($this->sentTo($target), $target->name);
        }
    }

    public function test_a_follow_up_is_never_sent_automatically(): void
    {
        $target = $this->drafted('Harmony Society');
        $this->postJson('/api/admin/outreach/messages/'.OutreachMessage::query()->where('outreach_target_id', $target->id)->sole()->sqid().'/send')->assertOk();
        $this->travel(11)->days();
        $this->travelTo(Carbon::parse('2026-11-23 10:00:00', 'Europe/London'));
        $this->actingAsAdmin($this->admin);
        $this->postJson('/api/admin/outreach/'.$target->sqid().'/drafts', ['kind' => 'follow_up'])->assertCreated();
        $this->switchOn();

        $this->turn();
        $this->turn();
        $this->assertSame('draft', OutreachMessage::query()->where('outreach_target_id', $target->id)->where('kind', OutreachMessage::FOLLOW_UP)->sole()->status);
        Mail::assertSentCount(1);
    }

    public function test_a_failed_send_skips_that_organization_instead_of_retrying(): void
    {
        $failing = $this->drafted('Failing Choir', ['sendBy' => '2026-11-11']);
        $this->switchOn();
        // The real mailer, and Cloudflare refusing: the send fails for real.
        Http::fake(['*' => Http::response(['success' => false], 500)]);
        Log::spy();

        $this->turn();
        $this->assertFalse($this->sentTo($failing));
        $this->assertSame('draft', OutreachMessage::query()->where('outreach_target_id', $failing->id)->sole()->status);
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'skipping that organization'))->once();

        // The next turns leave it alone for a week: no retry loop.
        $this->turn();
        $this->turn();
        Http::assertSentCount(1);
    }
}
