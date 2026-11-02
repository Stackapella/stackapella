<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Templates\CarryVoicesOver;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Wav;
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

    public function test_an_accepted_take_on_the_current_version_is_the_guide_a_learner_hears(): void
    {
        Notification::fake();
        $this->write([...$this->song(), 'version' => 1, 'reviewed' => true]);
        $this->artisan('app:templates:sync')->assertSuccessful();
        $take = $this->take('alto', 2.0);

        $this->actingAsAdmin();
        $this->postJson('/api/admin/beta/submissions/'.$take->sqid().'/accept', ['makeDefault' => true])->assertOk();

        // Anyone opening the library (signed out too) gets the voice for Sing.
        auth()->forgetGuards();
        $this->assertNotNull($this->catalogued('alto'));
        $this->assertNull($this->catalogued('tenor'));
    }

    public function test_a_breath_added_in_a_new_version_carries_takes_with_that_moment_silenced(): void
    {
        Notification::fake();
        $song = [...$this->song(), 'version' => 1, 'reviewed' => true];
        $this->write($song);
        $this->artisan('app:templates:sync')->assertSuccessful();
        $published = $this->take('alto', 6.0);
        $waiting = $this->take('tenor', 2.0);
        $this->actingAsAdmin();
        $this->postJson('/api/admin/beta/submissions/'.$published->sqid().'/accept', ['makeDefault' => true])->assertOk();

        // Version 2: the alto and tenor notes at beat 4 end a beat early (beat 5 instead of 6).
        $song['version'] = 2;
        foreach (['alto', 'tenor'] as $part) {
            $note = &$song['parts'][$this->part($song, $part)]['guide']['notes'][2];
            $this->assertSame([4, 2], [$note['beat'], $note['length']]);
            $note['length'] = 1;
            unset($note);
        }
        $this->write($song);
        $this->artisan('app:templates:sync')->expectsOutputToContain('Carried over')->assertSuccessful();
        $v2 = TemplateVersion::query()->where('version', 2)->sole();

        // The published take is made again on version 2, still the one learners hear.
        $guide = TemplateGuide::query()->where('beta_submission_id', $published->id)->sole();
        $this->assertSame($v2->id, $guide->template_version_id);
        $this->assertTrue($guide->is_default);
        $this->assertSame([[5, 6]], $published->refresh()->rests);
        $this->assertSame('published', $published->publish_status);
        auth()->forgetGuards();
        $this->assertNotNull($this->catalogued('alto'));

        // Beats 5 to 6 at 80 bpm are 3.75 to 4.5 seconds of song time: silent, with the notes around them kept.
        $this->assertSame(0, $guide->offset_frames);
        $pcm = (string) Process::run(['ffmpeg', '-v', 'error', '-i', Storage::disk('media')->path($guide->media_key), '-f', 's16le', '-ac', '1', '-ar', '48000', '-'])->throw()->output();
        $peak = static function (float $from, float $to) use ($pcm): int {
            $samples = (array) unpack('s*', substr($pcm, (int) ($from * 48000) * 2, (int) (($to - $from) * 48000) * 2));

            return (int) max(array_map('abs', $samples));
        };
        $this->assertLessThan(100, $peak(3.8, 4.45));
        $this->assertGreaterThan(3000, $peak(3.2, 3.7));
        $this->assertGreaterThan(3000, $peak(4.55, 5.0));

        // The take waiting for review moves too, so accepting it publishes it where learners hear it.
        $this->assertSame($v2->id, $waiting->refresh()->template_version_id);
        $this->assertSame([[5, 6]], $waiting->rests);

        // Again: nothing more happens.
        $this->artisan('app:templates:sync')->doesntExpectOutputToContain('Carried over')->assertSuccessful();
        $this->assertSame($guide->id, TemplateGuide::query()->where('beta_submission_id', $published->id)->sole()->id);
    }

    public function test_only_notes_made_shorter_count_as_a_breath(): void
    {
        $song = $this->song();
        $alto = $this->part($song, 'alto');

        $this->assertSame([], CarryVoicesOver::breaths($song, $song, 'alto'));

        $shorter = $song;
        $shorter['parts'][$alto]['guide']['notes'][2]['length'] = 1.5;
        $this->assertSame([[5.5, 6.0]], CarryVoicesOver::breaths($song, $shorter, 'alto'));

        $longer = $song;
        $longer['parts'][$alto]['guide']['notes'][2]['length'] = 2.5;
        $this->assertNull(CarryVoicesOver::breaths($song, $longer, 'alto'));

        $moved = $song;
        $moved['parts'][$alto]['guide']['notes'][2]['beat'] = 4.5;
        $this->assertNull(CarryVoicesOver::breaths($song, $moved, 'alto'));

        $pitch = $shorter;
        $pitch['parts'][$alto]['guide']['notes'][2]['pitch']++;
        $this->assertNull(CarryVoicesOver::breaths($song, $pitch, 'alto'));

        $this->assertNull(CarryVoicesOver::breaths($song, [...$shorter, 'tempo' => 81], 'alto'));
        $this->assertNull(CarryVoicesOver::breaths($song, $song, 'descant'));
    }

    /**
     * A beta singer's take of a part of Amazing Grace's current version, waiting for review: a
     * tone from beat 0.
     */
    private function take(string $part, float $seconds): BetaSubmission
    {
        $template = Template::query()->where('slug', 'amazing-grace')->firstOrFail();
        $assignment = BetaAssignment::factory()->create(['beta_singer_id' => BetaSinger::factory()->accepted()->consented()->create()->id, 'template_id' => $template->id, 'part_key' => $part]);
        $wav = Wav::tone($seconds);
        $take = BetaSubmission::factory()->create([
            'beta_assignment_id' => $assignment->id,
            'template_version_id' => $template->current_version_id,
            'part_key' => $part,
            'status' => SubmissionStatus::Submitted,
            'frames' => intdiv(strlen($wav) - 44, 2),
            'bytes' => strlen($wav),
            'sha256' => hash('sha256', $wav),
            'loudness_dbfs' => -13.5,
            'peaks' => [0.3, 0.3],
            'offset_ms' => 0,
            'trim_start_ms' => 0,
            'trim_end_ms' => 0,
        ]);
        Storage::disk('private')->put($take->master_key, $wav);

        return $take;
    }

    /**
     * The guide the library gives Sing for a part of Amazing Grace.
     *
     * @return array<string, mixed>|null
     */
    private function catalogued(string $part): ?array
    {
        $song = collect((array) $this->getJson('/api/templates')->assertOk()->json('data'))->firstWhere('id', 'amazing-grace');
        $this->assertIsArray($song);

        return collect((array) $song['parts'])->firstWhere('key', $part)['guide'] ?? null;
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
