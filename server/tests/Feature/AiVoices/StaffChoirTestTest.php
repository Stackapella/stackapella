<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\Counts\RecordCounts;
use App\Enums\OwnChoirStatus;
use App\Models\AuditLog;
use App\Models\FlagUser;
use App\Models\OwnChoirFreeSong;
use App\Models\OwnChoirRequest;
use App\Models\Template;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use App\Support\OwnChoir\OwnChoir;
use App\Support\OwnChoir\SyntheticClip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Staff's end-to-end test of the choir on production (2026-11-22): own_choir on for one staff account only, and a
 * console command that makes that account's song with a synthetic clip, as the app would, for the real worker.
 */
final class StaffChoirTestTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    private function staff(): User
    {
        return User::factory()->admin()->create(['email' => 'staff@example.com', 'store_plus_until' => now()->addDays(10)]);
    }

    public function test_a_feature_switch_goes_on_for_one_staff_account_only(): void
    {
        $staff = $this->staff();
        $someone = User::factory()->create(['email' => 'someone@example.com']);

        $this->artisan('app:flags:account', ['flag' => 'shield_mode', 'email' => 'staff@example.com'])->assertFailed();
        $this->artisan('app:flags:account', ['flag' => 'own_choir', 'email' => 'someone@example.com'])->assertFailed();
        $this->artisan('app:flags:account', ['flag' => 'own_choir', 'email' => 'staff@example.com'])->assertSuccessful();

        $this->assertTrue(OwnChoir::on($staff));
        $this->assertFalse(OwnChoir::on($someone));
        $this->actingAs($staff)->getJson('/api/auth/config')->assertJsonPath('data.ownChoir', true);
        $this->actingAs($someone)->getJson('/api/auth/config')->assertJsonPath('data.ownChoir', false);
        $this->assertTrue(AuditLog::query()->where('action', 'flag.account')->exists());

        $this->artisan('app:flags:account', ['flag' => 'own_choir', 'email' => 'staff@example.com', '--off' => true])->assertSuccessful();
        $this->assertSame(0, FlagUser::query()->count());
    }

    public function test_the_synthetic_clip_sings_the_parts_own_notes_and_no_one(): void
    {
        $this->catalogue();
        $data = (array) json_decode((string) DB::table('template_versions')->where('id', Template::query()->where('slug', 'amazing-grace')->value('current_version_id'))->value('data'), true);
        ['wav' => $wav, 'offsetFrames' => $offset] = SyntheticClip::make($data, 'soprano');
        $this->assertSame(24000, $offset, 'Beat 0 after half a second of silence.');
        $path = (string) tempnam(sys_get_temp_dir(), 'clip');
        file_put_contents($path, $wav);

        try {
            $probe = app(Ffmpeg::class)->probe($path, upload: false);
        } finally {
            @unlink($path);
        }

        $this->assertSame(['pcm_s16le', 1, 48000], [$probe['codec'] ?? null, $probe['channels'] ?? null, $probe['sampleRate'] ?? null]);
        $frames = (int) ($probe['frames'] ?? 0);
        $this->assertSame(44 + $frames * 2, strlen($wav), 'A plain 44-byte header.');
        $this->assertLessThanOrEqual(24 * 48000, $frames);
        $this->assertGreaterThan(12 * 48000, $frames);
        // Quiet at both edges (the worker's voice step reads a loud edge as a breath), singing between.
        $this->assertSame(str_repeat("\0", 24000 * 2), substr($wav, 44, 24000 * 2));
        $this->assertSame(str_repeat("\0", 24000 * 2), substr($wav, -24000 * 2));
        $this->assertNotSame(str_repeat("\0", 2000), substr($wav, 44 + 48000 * 2, 2000), 'It sings.');
    }

    public function test_a_staff_test_song_is_made_as_the_app_would_claimed_shown_and_cleaned_up(): void
    {
        $this->catalogue();
        Storage::fake('private');
        config(['services.ai_worker.choir_token' => self::TOKEN, 'services.turnstile.site_key' => 'site-key']);
        $staff = $this->staff();
        $someone = User::factory()->create(['email' => 'someone@example.com']);

        // Off for them: refused, and never for a member.
        $this->artisan('app:own-choir:test-request', ['email' => 'staff@example.com', '--song' => 'amazing-grace'])->assertFailed();
        FlagUser::query()->create(['flag' => 'own_choir', 'user_id' => $someone->id]);
        $this->artisan('app:own-choir:test-request', ['email' => 'someone@example.com', '--song' => 'amazing-grace'])->assertFailed();

        FlagUser::query()->create(['flag' => 'own_choir', 'user_id' => $staff->id]);
        $this->artisan('app:own-choir:test-request', ['email' => 'staff@example.com', '--song' => 'amazing-grace'])->assertSuccessful();

        $song = OwnChoirRequest::query()->sole();
        $this->assertSame(OwnChoirStatus::Queued, $song->status, 'Its clip checked, waiting for the worker.');
        $this->assertNotNull($song->clip_checked_at);
        $this->assertTrue(OwnChoir::consented($staff));
        $this->assertTrue(AuditLog::query()->where('action', 'own_choir.test_request')->exists());

        // The real worker API claims it.
        $claim = $this->json('POST', '/api/worker/choir/claim', [], ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();
        $claim->assertJsonPath('data.id', $song->sqid())->assertJsonPath('data.clip.part', 'soprano')->assertJsonPath('data.clip.offsetFrames', 24000);

        $this->artisan('app:own-choir:test-request', ['--status' => $song->sqid()])->expectsOutputToContain('"status": "rendering"')->assertSuccessful();
        $this->artisan('app:own-choir:test-request', ['--cleanup' => $song->sqid()])->assertSuccessful();
        $this->assertNull(OwnChoirRequest::query()->find($song->id), 'A test leaves nothing that counts.');
        $this->assertSame([], Storage::disk('private')->allFiles('uploads/choir'));
        $this->assertTrue(AuditLog::query()->where('action', 'own_choir.test_cleanup')->exists());
    }

    public function test_the_shared_choir_pages_link_is_counted_by_its_tag(): void
    {
        $this->assertSame('choir-share', RecordCounts::source('choir-share'));
    }

    public function test_a_free_staff_accounts_test_gives_its_free_song_back(): void
    {
        $this->catalogue();
        Storage::fake('private');
        $staff = User::factory()->admin()->create(['email' => 'staff@example.com']);
        FlagUser::query()->create(['flag' => 'own_choir', 'user_id' => $staff->id]);

        $this->artisan('app:own-choir:test-request', ['email' => 'staff@example.com', '--song' => 'amazing-grace'])->assertSuccessful();
        $song = OwnChoirRequest::query()->sole();
        $song->forceFill(['tries' => 1])->save();
        $this->assertSame(0, OwnChoir::quota($staff)['freeLeft'], 'The test took it.');

        $this->artisan('app:own-choir:test-request', ['--cleanup' => $song->sqid()])->assertSuccessful();

        $this->assertSame(1, OwnChoir::quota($staff)['freeLeft'], 'And gave it back.');
        $this->assertSame(0, OwnChoirFreeSong::query()->count());
    }
}
