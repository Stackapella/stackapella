<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\OwnChoir\ChoirJobs;
use App\Actions\Users\DeleteAccount;
use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirFreeSong;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\Template;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Support\Flags;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The GPU review (2026-11-21): what a choir song costs and who pays for it, the day's ceiling across everyone, the
 * checks before a free song, the claim kept alive with its GPU time, and Download at full quality with Plus only.
 */
final class GpuCostTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    /**
     * @param  array<string, mixed>  $with
     */
    private function singer(array $with = []): User
    {
        $user = User::factory()->create($with)->fresh() ?? throw new \LogicException;
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        OwnChoirVoice::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        return $user;
    }

    private function version(): int
    {
        return (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function song(User $user, array $with = []): OwnChoirRequest
    {
        return OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $this->version(), 'status' => OwnChoirStatus::Ready, 'tries' => 1, 'first_claimed_at' => now(), ...$with]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogue();
        app(Flags::class)->set('own_choir', 'on', null, 'Testing');
        config(['services.ai_worker.choir_token' => self::TOKEN, 'services.turnstile.site_key' => '', 'own_choir.daily' => 50]);
    }

    public function test_paid_plus_counts_part_minutes_and_says_what_a_song_needs(): void
    {
        // Bought in an app, renewing in ten days: its month began twenty-odd days ago.
        $paid = $this->singer(['store_plus_until' => now()->addDays(10)]);
        $needed = OwnChoir::partMinutes((array) json_decode((string) DB::table('template_versions')->where('id', $this->version())->value('data'), true));
        $this->assertGreaterThan(0, $needed);
        config(['own_choir.plus_minutes' => $needed * 1.5]);
        $this->song($paid, ['part_minutes' => $needed]);

        $quota = OwnChoir::quota($paid);
        $this->assertSame('minutes', $quota['unit']);
        $this->assertEqualsWithDelta($needed * 0.5, $quota['minutesLeft'], 0.1);
        $this->assertNotNull($quota['resetsAt']);

        $refused = $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace']);
        $this->assertMatchesContract($refused, 'ownChoir.requests.create', 403);
        $refused->assertJsonPath('which', 'minutes')->assertJsonPath('needed', $needed);

        // The subscription's own month, renewing with it (never the calendar month).
        $this->assertSame(now()->addDays(10)->utc()->toDateString(), substr((string) $quota['resetsAt'], 0, 10));
        // A song from before this month began counts no more.
        OwnChoirRequest::query()->update(['first_claimed_at' => now()->subDays(25), 'created_at' => now()->subDays(25)]);
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertCreated()->assertJsonPath('data.startsAfter', null);
        $this->assertSame(1, OwnChoirRequest::query()->where('priority', 1)->count(), 'Paid Plus goes first.');
    }

    public function test_plus_not_paid_for_has_a_few_songs_and_a_free_song_is_one_per_address(): void
    {
        $comped = $this->singer(['plan' => 'plus']);
        $this->assertSame('comped', OwnChoir::tier($comped));
        config(['own_choir.comped_songs' => 1]);
        $this->song($comped);
        $this->assertSame(['month', 'songs'], [OwnChoir::limitReached($comped), OwnChoir::quota($comped)['unit']]);

        // As one mailbox under two names (config own_choir.dotless_domains: Gmail's in production).
        config(['own_choir.dotless_domains' => ['example.com', 'example.net']]);
        $ada = $this->singer(['email' => 'ada.lovelace@example.com']);
        $this->song($ada, ['email_key' => OwnChoir::emailKey($ada)]);
        $again = $this->singer(['email' => 'adalovelace+choir@example.net']);

        $this->assertSame(OwnChoir::emailKey($ada), OwnChoir::emailKey($again));
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertForbidden()->assertJsonPath('which', 'free');
    }

    public function test_a_free_song_needs_a_confirmed_lasting_address_and_turnstile_first(): void
    {
        $unverified = User::factory()->withPassword()->unverified()->create()->fresh() ?? throw new \LogicException;
        OwnChoirConsent::factory()->create(['user_id' => $unverified->id]);
        $this->actingAs($unverified);
        $this->assertMatchesContract($this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace']), 'ownChoir.requests.create', 403);
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertJsonPath('code', 'email_unverified');

        // A throwaway address: changed in Settings first.
        $unverified->forceFill(['email' => 'someone@mailinator.com'])->save();
        $this->actingAs($unverified->fresh() ?? $unverified);
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertUnprocessable()->assertJsonValidationErrors('email');

        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret' => 'secret']);
        $this->singer();
        $this->getJson('/api/own-choir')->assertJsonPath('data.checkNeeded', true);
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertForbidden()->assertJsonPath('code', 'turnstile_required');
        Http::fake(['challenges.cloudflare.com/*' => fn ($request) => Http::response(['success' => $request['response'] === 'good'])]);
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace', 'turnstileToken' => 'bad'])->assertForbidden()->assertJsonPath('code', 'turnstile_failed');
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace', 'turnstileToken' => 'good'])->assertCreated();
        $this->getJson('/api/own-choir')->assertJsonPath('data.checkNeeded', false);
    }

    public function test_on_a_busy_day_a_song_waits_for_tomorrow_and_the_admins_are_told_once(): void
    {
        Notification::fake();
        config(['own_choir.daily_part_minutes' => 1, 'admin.admin_google_ids' => ['g-admin']]);
        $admin = User::factory()->admin()->create(['google_id' => 'g-admin', 'email' => 'admin@example.com']);
        $this->song(User::factory()->create(), ['part_minutes' => 2]);
        $this->singer();

        $this->getJson('/api/own-choir')->assertJsonPath('data.busyToday', true);
        $made = $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertCreated();
        $this->assertSame(now()->addDay()->startOfDay()->utc()->toIso8601ZuluString(), $made->json('data.startsAfter'));

        $headers = ['Authorization' => 'Bearer '.self::TOKEN];
        $this->json('POST', '/api/worker/choir/claim', [], $headers)->assertNoContent();
        $this->json('POST', '/api/worker/choir/claim', [], $headers)->assertNoContent();
        Notification::assertSentToTimes($admin, OpsAlert::class, 1);

        $this->travelTo(now()->addDay()->startOfDay()->addMinute());
        $claimed = $this->json('POST', '/api/worker/choir/claim', [], $headers)->assertOk();
        $this->assertMatchesContract($claimed, 'worker.choir.claim', 200);
        $claimed->assertJsonPath('data.own', false);
    }

    public function test_a_claim_is_kept_alive_up_to_an_hour_and_its_gpu_time_is_summed(): void
    {
        $user = $this->singer();
        $job = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $this->version(), 'status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_started_at' => now(), 'claim_token' => str_repeat('a', 32), 'tries' => 1, 'gpu_seconds' => 100, 'claim_gpu_seconds' => 0]);
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];
        $url = "/api/worker/choir/jobs/{$job->sqid()}/extend";

        $this->assertMatchesContract($this->json('POST', $url, ['claim' => str_repeat('a', 32), 'gpuSeconds' => 50], $headers), 'worker.choir.jobs.extend', 200);
        // A smaller number later never takes time back; a bigger one adds the difference.
        $this->json('POST', $url, ['claim' => str_repeat('a', 32), 'gpuSeconds' => 40], $headers)->assertOk();
        $this->json('POST', $url, ['claim' => str_repeat('a', 32), 'gpuSeconds' => 80], $headers)->assertOk();
        $this->assertEqualsWithDelta(180.0, $job->refresh()->gpu_seconds, 0.01);
        $this->assertMatchesContract($this->json('POST', $url, ['claim' => str_repeat('b', 32), 'gpuSeconds' => 1], $headers), 'worker.choir.jobs.extend', 409);

        $this->travel(61)->minutes();
        $job->forceFill(['claimed_at' => now()])->save();
        $this->json('POST', $url, ['claim' => str_repeat('a', 32), 'gpuSeconds' => 90], $headers)->assertStatus(409)->assertJsonPath('code', 'claim_limit');
        $this->assertMatchesContract($this->json('POST', $url, ['claim' => str_repeat('a', 32)], $headers), 'worker.choir.jobs.extend', 422);
    }

    public function test_a_gates_failure_on_an_own_song_is_the_singers(): void
    {
        $user = $this->singer();
        config(['own_choir.free_songs' => 1]);
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'gates_failed', 'own' => true]);
        $this->assertSame(0, OwnChoir::quota($user)['freeLeft']);

        OwnChoirRequest::query()->update(['own' => false]);
        $this->assertSame(1, OwnChoir::quota($user)['freeLeft'], 'A library song\'s gates failure is ours.');
    }

    public function test_download_is_full_quality_with_plus_and_listening_is_the_light_copy(): void
    {
        $user = $this->singer();
        $song = $this->song($user);
        $part = OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'listen_key' => 'choir/parts/listen.m4a']);

        $this->assertStringContainsString('listen.m4a', (string) $this->getJson('/api/own-choir')->json('data.requests.0.parts.0.url'));
        $this->assertMatchesContract($this->getJson("/api/own-choir/requests/{$song->sqid()}/download"), 'ownChoir.requests.download', 403);

        $user->forceFill(['store_plus_until' => now()->addMonth()])->save();
        $this->actingAs($user->fresh() ?? $user);
        $download = $this->getJson("/api/own-choir/requests/{$song->sqid()}/download");
        $this->assertMatchesContract($download, 'ownChoir.requests.download', 200);
        $this->assertStringContainsString(basename((string) $part->media_key), (string) $download->json('data.parts.0.url'));
        $this->assertMatchesContract($this->getJson('/api/own-choir/requests/nope/download'), 'ownChoir.requests.download', 404);
    }

    public function test_the_workers_pause_is_kept_and_the_admins_told_once(): void
    {
        Notification::fake();
        config(['admin.admin_google_ids' => ['g-admin']]);
        $admin = User::factory()->admin()->create(['google_id' => 'g-admin', 'email' => 'admin@example.com']);
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];
        $paused = ['paused' => 'daily_ceiling', 'spentTodayUsd' => 5.02, 'spentMonthUsd' => 41.7, 'limitDayUsd' => 5, 'limitMonthUsd' => 100];

        $this->json('POST', '/api/worker/choir/status', $paused, $headers)->assertNoContent();
        $this->json('POST', '/api/worker/choir/status', $paused, $headers)->assertNoContent();
        $this->json('POST', '/api/worker/choir/status', [...$paused, 'paused' => null], $headers)->assertNoContent();
        $this->assertMatchesContract($this->json('POST', '/api/worker/choir/status', [...$paused, 'paused' => 'weekly'], $headers), 'worker.choir.status', 422);

        Notification::assertSentToTimes($admin, OpsAlert::class, 1);
    }

    public function test_the_migration_fills_part_minutes_once(): void
    {
        $song = $this->song(User::factory()->create(), ['part_minutes' => 0]);
        $migration = require base_path('database/migrations/2026_11_21_110000_add_cost_to_own_choir_requests.php');

        $migration->up();
        $filled = $song->refresh()->part_minutes;
        $migration->up();

        $this->assertGreaterThan(0, $filled);
        $this->assertSame($filled, $song->refresh()->part_minutes);
    }

    public function test_the_free_song_is_once_per_address_even_after_the_account_is_deleted(): void
    {
        $ada = $this->singer(['google_id' => 'g-ada-1']);
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertCreated();
        $this->assertSame(2, OwnChoirFreeSong::query()->count(), 'The address and the Google id.');
        // Made (it reached the renderer): a song that never did would be given back.
        OwnChoirRequest::query()->update(['status' => OwnChoirStatus::Ready->value, 'tries' => 1]);

        app(DeleteAccount::class)->handle($ada->fresh() ?? $ada, '203.0.113.5');

        // Only the hashes stay: no request, voice or consent, and nothing that names them.
        $this->assertSame(0, OwnChoirRequest::query()->count());
        $this->assertSame(0, OwnChoirVoice::query()->count());
        $this->assertSame(0, OwnChoirConsent::query()->count());
        $this->assertSame([null, null], OwnChoirFreeSong::query()->pluck('own_choir_request_id')->all());
        // One-way hashes only: 64 hex digits, never the address or the sign-in id.
        foreach (OwnChoirFreeSong::query()->pluck('key_hash') as $hash) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $hash);
            $this->assertStringNotContainsString((string) $ada->email, (string) $hash);
        }

        // Signed up again with the same address, or the same Google account under another: no second free song.
        $this->singer(['email' => (string) $ada->email, 'google_id' => null]);
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertForbidden()->assertJsonPath('which', 'free');
        $this->singer(['google_id' => 'g-ada-1']);
        $this->getJson('/api/own-choir')->assertJsonPath('data.quota.freeLeft', 0);
    }

    public function test_a_free_song_that_fails_through_ours_is_given_back(): void
    {
        $user = $this->singer();
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertCreated();
        $song = OwnChoirRequest::query()->firstOrFail();
        $song->forceFill(['status' => OwnChoirStatus::Rendering, 'tries' => 1])->save();

        ChoirJobs::failAndClean($song, 'render_failed');

        $this->assertSame(0, OwnChoirFreeSong::query()->count());
        $this->assertSame(1, OwnChoir::quota($user)['freeLeft']);
    }
}
