<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Templates\CarryVoicesOver;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A new version of a song carries each recorded voice whose part sings the same, and tells Hillel
 * once what became of each part.
 */
final class CarryVoicesOverTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = storage_path('framework/testing/templates-'.uniqid());
        File::ensureDirectoryExists($this->folder);
        config(['templates.folders' => [$this->folder]]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_a_voice_whose_part_sings_the_same_moves_to_the_new_version_and_hillel_hears_once(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $song = [...$this->song(), 'version' => 1, 'reviewed' => true];
        $this->write($song);
        $this->artisan('app:templates:sync')->assertSuccessful();
        $v1 = TemplateVersion::query()->sole();

        $soprano = TemplateGuide::factory()->create(['template_version_id' => $v1->id, 'part_key' => 'soprano', 'beta_submission_id' => BetaSubmission::factory()->create(['template_version_id' => $v1->id, 'part_key' => 'soprano', 'status' => SubmissionStatus::Accepted])->id]);
        $alto = TemplateGuide::factory()->create(['template_version_id' => $v1->id, 'part_key' => 'alto']);
        $tenor = TemplateGuide::factory()->create(['template_version_id' => $v1->id, 'part_key' => 'tenor']);
        $bass = BetaSubmission::factory()->create(['template_version_id' => $v1->id, 'part_key' => 'bass', 'status' => SubmissionStatus::Accepted]);

        // Version 2: the soprano's words change (only), the alto's notes change.
        $song['version'] = 2;
        $song['parts'][$this->part($song, 'soprano')]['guide']['notes'][0]['lyric'] = 'Oh';
        $song['parts'][$this->part($song, 'alto')]['guide']['notes'][0]['pitch']++;
        $this->write($song);
        $this->artisan('app:templates:sync')->expectsOutputToContain('Carried over')->assertSuccessful();
        $v2 = TemplateVersion::query()->where('version', 2)->sole();

        $this->assertSame($v2->id, $soprano->refresh()->template_version_id);
        $this->assertSame($v2->id, BetaSubmission::query()->findOrFail($soprano->beta_submission_id)->template_version_id);
        $this->assertTrue($soprano->is_default);
        $this->assertSame($v2->id, $tenor->refresh()->template_version_id);
        $this->assertSame($v1->id, $alto->refresh()->template_version_id);
        $this->assertSame($v2->id, $bass->refresh()->template_version_id);
        $this->assertSame(1, AuditLog::query()->where('action', CarryVoicesOver::CARRIED)->count());

        Notification::assertSentToTimes($admin, OpsAlert::class, 1);
        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => array_slice($alert->lines, 1, 4) === [
            __('ops.voices_carried.carried', ['part' => 'Soprano']),
            __('ops.voices_carried.changed', ['part' => 'Alto']),
            __('ops.voices_carried.carried', ['part' => 'Tenor']),
            __('ops.voices_carried.carried', ['part' => 'Bass']),
        ]);

        // Again: nothing moves, and nothing more is said.
        $this->artisan('app:templates:sync')->doesntExpectOutputToContain('Carried over')->assertSuccessful();
        Notification::assertSentToTimes($admin, OpsAlert::class, 1);
        $this->assertSame($v1->id, $alto->refresh()->template_version_id);
        $this->assertSame(1, AuditLog::query()->where('action', CarryVoicesOver::CARRIED)->count());
    }

    public function test_a_part_sings_the_same_only_with_the_same_notes_key_and_tempo(): void
    {
        $song = $this->song();
        $alto = $this->part($song, 'alto');

        $words = $song;
        $words['parts'][$alto]['guide']['notes'][0]['lyric'] = 'Oh';
        $this->assertTrue(CarryVoicesOver::sameMusic($song, $words, 'alto'));

        $note = $song;
        $note['parts'][$alto]['guide']['notes'][0]['length'] += 0.5;
        $this->assertFalse(CarryVoicesOver::sameMusic($song, $note, 'alto'));
        $this->assertTrue(CarryVoicesOver::sameMusic($song, $note, 'soprano'));

        $this->assertFalse(CarryVoicesOver::sameMusic($song, [...$song, 'key' => 'A'], 'alto'));
        $this->assertFalse(CarryVoicesOver::sameMusic($song, [...$song, 'tempo' => 81], 'alto'));
        $this->assertFalse(CarryVoicesOver::sameMusic($song, $song, 'descant'));
    }

    /**
     * @return array<string, mixed>
     */
    private function song(): array
    {
        return (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
    }

    /**
     * @param  array<string, mixed>  $song
     */
    private function part(array $song, string $key): int
    {
        return (int) collect((array) $song['parts'])->search(fn (array $part): bool => $part['key'] === $key);
    }

    /**
     * @param  array<string, mixed>  $song
     */
    private function write(array $song): void
    {
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($song));
    }
}
