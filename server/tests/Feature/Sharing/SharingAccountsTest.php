<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Actions\Users\DeleteAccount;
use App\Jobs\BuildExport;
use App\Models\DataExport;
use App\Models\Harmony;
use App\Models\Report;
use App\Models\User;
use App\Support\Sharing\HarmonyFiles;
use App\Support\Trust\Limits;
use App\Support\Trust\TrustLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Sharing where it meets accounts: sign-ups from a share, the share limit by trust level, deleting
 * an account, and the data export.
 */
final class SharingAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function live(?User $owner = null): Harmony
    {
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => ($owner ?? User::factory()->create())->id]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    public function test_a_sign_up_from_a_shared_page_is_recorded_with_the_harmony(): void
    {
        Mail::fake();
        $harmony = $this->live();

        $this->postJson('/api/auth/register', ['name' => 'Bea', 'email' => 'bea@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'ref' => 'h-'.$harmony->public_id, 'elapsedMs' => 45000, 'nickname' => ''])->assertStatus(202);

        $bea = User::query()->where('email', 'bea@example.com')->firstOrFail();
        $this->assertSame($harmony->id, $bea->signup_harmony_id);
        $this->assertSame(1, $harmony->fresh()?->sign_ups);

        // Any other ref, or a harmony that isn't there, records nothing.
        $this->postJson('/api/auth/register', ['name' => 'Cy', 'email' => 'cy@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'ref' => 'h-nope', 'elapsedMs' => 45000, 'nickname' => ''])->assertStatus(202);
        $this->assertNull(User::query()->where('email', 'cy@example.com')->value('signup_harmony_id'));
    }

    public function test_the_share_limit_follows_the_trust_level(): void
    {
        $limits = app(Limits::class);
        $verified = User::factory()->create()->fresh() ?? throw new \LogicException;
        $trusted = User::factory()->create(['plus_paid_at' => now()])->fresh() ?? throw new \LogicException;
        $this->assertSame(TrustLevel::TRUSTED, app(TrustLevel::class)->of($trusted));

        foreach (range(1, 3) as $i) {
            $this->assertNull($limits->attempt('shares', $verified, '203.0.113.1'));
        }
        $this->assertNotNull($limits->attempt('shares', $verified, '203.0.113.1'));

        foreach (range(1, 50) as $i) {
            $this->assertNull($limits->attempt('shares', $trusted, '203.0.113.1'));
        }
        $this->assertNotNull($limits->attempt('shares', $trusted, '203.0.113.1'));
    }

    public function test_deleting_an_account_takes_its_shared_pages_and_their_reports_with_it(): void
    {
        $owner = User::factory()->create();
        $harmony = $this->live($owner);
        Report::factory()->create(['reportable_id' => $harmony->id]);
        $keys = [(string) $harmony->audio_key, (string) $harmony->card_key];
        $media = (string) $harmony->media_audio_key;

        app(DeleteAccount::class)->handle($owner->fresh() ?? $owner, '203.0.113.1');

        $this->assertSame(0, Harmony::query()->count());
        $this->assertSame(0, Report::query()->count());
        Storage::disk('media')->assertMissing($media);
        foreach ($keys as $key) {
            Storage::disk('private')->assertMissing($key);
        }
    }

    public function test_the_export_lists_shared_harmonies_with_a_link_to_each_mix(): void
    {
        Mail::fake();
        $owner = User::factory()->create()->fresh() ?? throw new \LogicException;
        $harmony = $this->live($owner);
        $this->actingAs($owner)->postJson('/api/account/export')->assertStatus(202);
        (new BuildExport((int) DataExport::query()->value('id')))->handle();

        $zip = new ZipArchive;
        $path = (string) tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($path, (string) Storage::disk('private')->get((string) DataExport::query()->value('file_key')));
        $zip->open($path);
        $shared = json_decode((string) $zip->getFromName('shared-harmonies.json'), true);
        $zip->close();
        @unlink($path);

        $this->assertSame($harmony->public_id, $shared[0]['id'] ?? null);
        $this->assertNotNull($shared[0]['mix'] ?? null);
    }
}
