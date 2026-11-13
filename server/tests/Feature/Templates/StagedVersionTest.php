<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\Templates\Staging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * A song's staged next version (docs/decisions.md, 2026-11-12, Amazing Grace: "can we keep the current ones but fix when
 * someone re-records?"): `<id>.next.json` beside the live file, never current, that new recordings target; only its
 * changed parts are wanted, the others' voices move over at the switch.
 */
final class StagedVersionTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private string $folder;

    /** @var array<string, mixed> */
    private array $live;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = storage_path('framework/testing/staged-'.getmypid());
        File::deleteDirectory($this->folder);
        File::ensureDirectoryExists($this->folder);
        config(['templates.folders' => [$this->folder]]);
        $this->live = [...(array) json_decode((string) File::get(dirname(base_path()).'/web/templates/amazing-grace.json'), true), 'reviewed' => true];
        File::put($this->folder.'/amazing-grace.json', (string) json_encode($this->live));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    /**
     * The live song with its alto's first note sung at its second's pitch, one version on.
     *
     * @return array<string, mixed>
     */
    private function next(array $changes = []): array
    {
        $next = [...$this->live, 'version' => $this->live['version'] + 1, 'reviewed' => false];
        foreach ($next['parts'] as $i => $part) {
            if ($part['key'] === 'alto') {
                $next['parts'][$i]['guide']['notes'][0]['pitch'] = $part['guide']['notes'][1]['pitch'] === $part['guide']['notes'][0]['pitch'] ? $part['guide']['notes'][0]['pitch'] + 2 : $part['guide']['notes'][1]['pitch'];
            }
        }

        return [...$next, ...$changes];
    }

    private function stage(array $next): array
    {
        File::put($this->folder.'/amazing-grace.next.json', (string) json_encode($next));

        return app(SyncOfficialTemplates::class)->handle();
    }

    private function song(): Template
    {
        return Template::query()->with(['currentVersion', 'stagedVersion'])->where('slug', 'amazing-grace')->firstOrFail();
    }

    public function test_the_sync_stages_a_next_version_beside_the_live_one_and_clears_it_at_the_switch(): void
    {
        $report = $this->stage($this->next());
        $this->assertSame([], $report['problems']);
        $song = $this->song();
        $this->assertSame($this->live['version'], $song->currentVersion?->version, 'the live version stays current');
        $this->assertSame($this->live['version'] + 1, $song->stagedVersion?->version);
        $this->assertSame($song->staged_version_id, $song->recordingVersion()?->id);
        $this->assertSame(['alto'], Staging::changed($song));

        // The switch: the staged file, reviewed, replaces the live one. Nothing is staged and the new version is current.
        File::delete($this->folder.'/amazing-grace.next.json');
        File::put($this->folder.'/amazing-grace.json', (string) json_encode([...$this->next(), 'reviewed' => true]));
        app(SyncOfficialTemplates::class)->handle();
        $this->assertNull($this->song()->staged_version_id);
        $this->assertSame($this->live['version'] + 1, $this->song()->currentVersion?->version);
    }

    public function test_a_staged_version_is_never_reviewed_and_always_newer(): void
    {
        $report = $this->stage($this->next(['reviewed' => true]));
        $this->assertStringContainsString('a staged version is never reviewed', implode("\n", $report['problems']));
        $this->assertNull($this->song()->staged_version_id);
        $this->assertSame($this->live['version'], $this->song()->currentVersion?->version, 'it never went live by itself');

        $report = $this->stage($this->next(['version' => $this->live['version'] - 1]));
        $this->assertStringContainsString("isn't newer than the live", implode("\n", $report['problems']));
        $this->assertNull($this->song()->staged_version_id);
    }

    public function test_a_singer_records_the_staged_version_and_hillel_reviews_it_there(): void
    {
        $this->stage($this->next());
        $staged = $this->live['version'] + 1;
        [$singer, $assignment] = $this->singerWithAlto();

        $parts = $this->asSinger($singer)->getJson('/api/beta/parts')->assertOk();
        $this->assertMatchesContract($parts, 'beta.parts.list', 200);
        $parts->assertJsonPath('data.0.template.version', $staged)->assertJsonPath('data.0.template.reviewed', false)->assertJsonPath('data.0.recordable', true);

        // The app loads the notes it records by that version (frontend's beta.js), unreviewed as it is.
        $this->asSinger($singer)->getJson('/api/templates/amazing-grace?version='.$staged)->assertOk()->assertJsonPath('data.template.version', $staged);

        $wav = Wav::tone(0.2);
        $take = ['assignmentId' => $assignment->sqid(), 'key' => (string) $this->live['key'], 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'latencyFrames' => 960, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav), 'device' => 'Mac, Chrome', 'mic' => 'Wired', 'scores' => ['pitchPercent' => 92], 'note' => ''];
        $this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'templateVersion' => $this->live['version']])->assertStatus(409)->assertJsonPath('code', 'template_changed');
        $this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'templateVersion' => $staged])->assertSuccessful();

        // Hillel's guide list: the staged version and its changed parts, and a take sent for it waiting for review.
        BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $this->song()->staged_version_id, 'status' => SubmissionStatus::Submitted]);
        $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/beta/guides')->assertOk();
        $this->assertMatchesContract($list, 'admin.beta.guides.list', 200);
        $list->assertJsonPath('data.0.staged', ['version' => $staged, 'changed' => ['alto']]);
        $alto = collect($list->json('data.0.parts'))->firstWhere('key', 'alto');
        $this->assertCount(1, $alto['needs']['review']);
        $this->assertSame($staged, $alto['needs']['review'][0]['version'], 'a take says which version it is for');
    }

    public function test_only_the_changed_parts_are_wanted_and_the_others_voices_stay_live(): void
    {
        $this->stage($this->next());
        $song = $this->song();
        TemplateGuide::factory()->create(['template_version_id' => $song->current_version_id, 'part_key' => 'bass', 'is_default' => true]);

        // The bass sings the same in the staged version: not wanted; its live voice stays where learners hear it.
        $this->assertNotContains('bass', Staging::changed($song));
        $this->assertSame(1, TemplateGuide::query()->where('template_version_id', $song->current_version_id)->where('part_key', 'bass')->count());
    }
}
