<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\Report;
use App\Models\User;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function live(): Harmony
    {
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => User::factory()->create()->id]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function report(Harmony $harmony, string $ip, array $with = []): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/reports', ['harmonyId' => $harmony->public_id, 'reason' => 'copyright', 'note' => 'This is my recording.', 'website' => '', ...$with]);
    }

    public function test_anyone_can_report_and_three_different_people_hide_the_page_until_hillel_looks(): void
    {
        $harmony = $this->live();
        $media = (string) $harmony->media_audio_key;

        $this->assertMatchesContract($this->report($harmony, '203.0.113.1'), 'reports.create', 204);
        // The same network again changes nothing.
        $this->report($harmony, '203.0.113.1')->assertNoContent();
        $this->report($harmony, '203.0.113.2')->assertNoContent();
        $this->assertNull($harmony->fresh()?->hidden_at);
        Storage::disk('media')->assertExists($media);

        // Signed in on a network that already reported: still the same person.
        $this->actingAs(User::factory()->create());
        $this->report($harmony, '203.0.113.2')->assertNoContent();
        $this->assertNull($harmony->fresh()?->hidden_at);
        $this->actingAs(User::factory()->create());
        $this->report($harmony, '203.0.113.3')->assertNoContent();

        $harmony->refresh();
        $this->assertSame(Harmony::HIDDEN_REPORTS, $harmony->hidden_reason);
        $this->assertSame('under_review', $harmony->moderation());
        Storage::disk('media')->assertMissing($media);
        $this->assertSame(4, Report::query()->where('status', Report::OPEN)->count());
        // A hidden page can't be reported any more: it answers as if it weren't there.
        $this->report($harmony, '203.0.113.4')->assertNotFound();
    }

    public function test_signed_out_reports_alone_go_to_the_queue_but_never_hide_the_page(): void
    {
        $harmony = $this->live();

        foreach (range(1, 5) as $i) {
            $this->report($harmony, '198.51.100.'.$i)->assertNoContent();
        }

        $this->assertNull($harmony->fresh()?->hidden_at);
        $this->assertSame(5, Report::query()->where('status', Report::OPEN)->count());
    }

    public function test_throwaway_accounts_on_one_network_and_unverified_accounts_count_as_one_or_none(): void
    {
        $harmony = $this->live();

        foreach (range(1, 3) as $i) {
            $this->actingAs(User::factory()->create());
            $this->report($harmony, '203.0.113.30')->assertNoContent();
        }
        foreach (range(1, 3) as $i) {
            $this->actingAs(User::factory()->withPassword()->unverified()->create());
            $this->report($harmony, '203.0.113.'.(40 + $i))->assertNoContent();
        }

        $this->assertSame(6, Report::query()->count());
        $this->assertNull($harmony->fresh()?->hidden_at);
    }

    public function test_your_own_harmonys_reports_and_the_honeypot_are_ignored_with_the_same_answer(): void
    {
        $harmony = $this->live();

        $this->report($harmony, '203.0.113.5', ['website' => 'https://spam.example'])->assertNoContent();
        $this->report($harmony, '203.0.113.5', ['website' => str_repeat('x', 2000)])->assertNoContent();
        $this->actingAs($harmony->user);
        $this->report($harmony, '203.0.113.6')->assertNoContent();

        $this->assertSame(0, Report::query()->count());
    }

    public function test_only_a_shared_harmony_can_be_reported_and_the_form_is_checked(): void
    {
        $private = Harmony::factory()->private()->create(['project_id' => null]);

        $this->assertMatchesContract($this->report($private, '203.0.113.7'), 'reports.create', 404);
        $this->postJson('/api/reports', ['harmonyId' => 'nope', 'reason' => 'copyright'])->assertNotFound();

        $bad = $this->report($this->live(), '203.0.113.7', ['reason' => 'boring', 'note' => str_repeat('x', 501)]);
        $this->assertMatchesContract($bad, 'reports.create', 422);
        $bad->assertJsonValidationErrors(['reason', 'note']);
    }

    public function test_reports_are_limited_per_network_and_need_turnstile_in_shield_mode(): void
    {
        $harmony = $this->live();

        foreach (range(1, 10) as $i) {
            $this->report($harmony, '198.51.100.9')->assertNoContent();
        }
        $this->assertMatchesContract($this->report($harmony, '198.51.100.9'), 'reports.create', 429);

        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret' => 'secret']);
        app(Flags::class)->set('shield_mode', 'on', null, 'Report flood');
        $this->assertMatchesContract($this->report($harmony, '198.51.100.10'), 'reports.create', 403);

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        $failed = $this->report($harmony, '198.51.100.11', ['turnstileToken' => 'bad']);
        $this->assertMatchesContract($failed, 'reports.create', 403);
        $failed->assertJsonPath('code', 'turnstile_failed');
    }

    public function test_the_queue_lists_reported_and_held_harmonies_and_hillel_can_hide_restore_and_remove(): void
    {
        $reported = $this->live();
        foreach (['203.0.113.21', '203.0.113.22'] as $ip) {
            $this->report($reported, $ip)->assertNoContent();
        }
        $this->actingAs(User::factory()->create());
        $this->report($reported, '203.0.113.23')->assertNoContent();
        $this->post('/api/auth/sign-out');
        $held = Harmony::factory()->create(['project_id' => null, 'title' => 'free money', 'title_held_at' => now()]);
        Harmony::factory()->create(['project_id' => null]);

        $admin = $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/reports');
        $this->assertMatchesContract($list, 'admin.reports.list', 200);
        $list->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.harmony.id', $reported->public_id)
            ->assertJsonPath('data.0.harmony.moderation', 'under_review')
            ->assertJsonPath('data.0.reports.0.reporter', null)
            ->assertJsonPath('data.1.harmony.titleHeld', true);

        $restored = $this->postJson('/api/admin/harmonies/'.$reported->public_id.'/restore', ['note' => 'A cover of a public domain song.']);
        $this->assertMatchesContract($restored, 'admin.harmonies.restore', 200);
        $this->assertNull($reported->user->fresh()?->trust_lowered_until);
        $restored->assertJsonPath('data.harmony.moderation', null)->assertJsonPath('data.reports.0.status', 'dismissed');
        Storage::disk('media')->assertExists((string) $reported->fresh()?->media_audio_key);

        $hidden = $this->postJson('/api/admin/harmonies/'.$held->public_id.'/hide', ['reason' => 'dmca', 'note' => 'Notice from the label, 2026-10-26.']);
        $this->assertMatchesContract($hidden, 'admin.harmonies.hide', 200);
        $hidden->assertJsonPath('data.harmony.moderation', 'hidden')->assertJsonPath('data.harmony.hiddenReason', 'dmca')->assertJsonPath('data.harmony.ownerTakedowns', 1);
        $this->assertTrue($held->user->fresh()?->trust_lowered_until?->isFuture());
        $this->assertSame(1, AuditLog::query()->where('action', 'harmony.hidden')->count());

        $this->assertMatchesContract($this->postJson('/api/admin/harmonies/'.$held->public_id.'/hide', ['reason' => 'boring']), 'admin.harmonies.hide', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/harmonies/nope/hide', ['reason' => 'admin', 'note' => 'x']), 'admin.harmonies.hide', 404);
        $this->assertMatchesContract($this->postJson('/api/admin/harmonies/nope/restore'), 'admin.harmonies.restore', 404);

        $removed = $this->postJson('/api/admin/harmonies/'.$held->public_id.'/remove', ['reason' => 'dmca', 'note' => 'No counter-notice.']);
        $this->assertMatchesContract($removed, 'admin.harmonies.remove', 200);
        $removed->assertJsonPath('data.harmony.moderation', 'removed')->assertJsonPath('data.harmony.audioUrl', null);
        Storage::disk('private')->assertMissing((string) $held->audio_key);
        $this->assertMatchesContract($this->postJson('/api/admin/harmonies/'.$held->public_id.'/restore'), 'admin.harmonies.restore', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/harmonies/nope/remove', ['reason' => 'admin', 'note' => 'x']), 'admin.harmonies.remove', 404);
        $this->assertMatchesContract($this->postJson('/api/admin/harmonies/'.$held->public_id.'/remove', []), 'admin.harmonies.remove', 422);

        $closed = $this->getJson('/api/admin/reports?status=closed');
        $closed->assertJsonCount(2, 'data')->assertJsonPath('data.0.harmony.id', $held->public_id);
        $this->getJson('/api/admin/reports')->assertJsonCount(0, 'data');
        $this->assertNotNull($admin);
    }

    public function test_suspending_the_owner_hides_what_they_shared_and_lifting_it_brings_it_back(): void
    {
        $harmony = $this->live();
        $this->actingAsAdmin();

        $this->postJson('/api/admin/people/'.$harmony->user->sqid().'/suspend', ['reason' => 'Spam'])->assertOk();
        $this->assertNull($harmony->fresh()?->media_audio_key);

        $this->postJson('/api/admin/people/'.$harmony->user->sqid().'/unsuspend', ['reason' => 'Sorted'])->assertOk();
        $this->assertNotNull($harmony->fresh()?->media_audio_key);
    }
}
