<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Actions\Beta\ForgetSinger;
use App\Actions\Courses\CourseClips;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CourseClip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The courses' real-voice clips: synced from clips.json, asked of a beta singer like a part,
 * recorded, accepted and published for the course's steps.
 */
final class CourseClipsTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    /**
     * @param  list<mixed>  $clips
     * @return list<string>
     */
    private function sync(array $clips): array
    {
        $path = sys_get_temp_dir().'/clips-'.getmypid().'.json';
        file_put_contents($path, (string) json_encode(['clips' => $clips]));
        config(['templates.clips' => $path]);

        try {
            return app(CourseClips::class)->sync();
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function hold(string $id): array
    {
        return ['id' => $id, 'course' => 'vibrato', 'lesson' => 'vib-1-hear', 'step' => 2, 'sing' => ['en' => 'One held "ah" with easy vibrato'], 'pitch' => ['step' => 0], 'seconds' => 3, 'style' => 'vibrato'];
    }

    /**
     * @return array{0: BetaSinger, 1: BetaAssignment, 2: CourseClip}
     */
    private function singerWithClip(): array
    {
        $clip = CourseClip::factory()->create(['slot' => 'vibrato.hold']);
        $singer = BetaSinger::factory()->accepted()->consented()->create();
        $assignment = BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'template_id' => null, 'part_key' => null, 'course_clip_id' => $clip->id]);

        return [$singer, $assignment, $clip];
    }

    public function test_the_sync_makes_updates_and_retires_slots_and_names_bad_entries(): void
    {
        $problems = $this->sync([$this->hold('vibrato.hold'), $this->hold('vibrato.wobble'), ['id' => 'Vibrato Bad', 'course' => 'vibrato'], [...$this->hold('vibrato.hold'), 'seconds' => 2], [...$this->hold('vibrato.range'), 'pitch' => ['low' => 3, 'high' => 1]]]);

        $this->assertCount(3, $problems);
        $this->assertStringContainsString('the id is used twice', implode("\n", $problems));
        $this->assertSame(['vibrato.hold', 'vibrato.wobble'], CourseClip::query()->orderBy('slot')->pluck('slot')->all());
        $this->assertSame(3.0, (float) CourseClip::query()->where('slot', 'vibrato.hold')->firstOrFail()->brief['seconds']);

        // The wobble leaves the file: retired, not deleted; it comes back when it's listed again.
        $this->assertSame([], $this->sync([[...$this->hold('vibrato.hold'), 'sing' => ['en' => 'Two held notes']]]));
        $this->assertNotNull(CourseClip::query()->where('slot', 'vibrato.wobble')->firstOrFail()->retired_at);
        $this->assertSame('Two held notes', CourseClip::query()->where('slot', 'vibrato.hold')->firstOrFail()->brief['sing']['en']);

        $this->sync([$this->hold('vibrato.hold'), $this->hold('vibrato.wobble')]);
        $this->assertNull(CourseClip::query()->where('slot', 'vibrato.wobble')->firstOrFail()->retired_at);
    }

    public function test_hillel_asks_a_singer_for_a_clip_once(): void
    {
        $this->actingAsAdmin();
        CourseClip::factory()->create(['slot' => 'vibrato.hold']);
        $singer = BetaSinger::factory()->accepted()->create();

        $created = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/assignments", ['clipId' => 'vibrato.hold', 'minutes' => 10]);
        $this->assertMatchesContract($created, 'admin.beta.assignments.create', 201);
        $created->assertJsonPath('data.kind', 'clip')->assertJsonPath('data.clipId', 'vibrato.hold')->assertJsonPath('data.templateId', null);

        $again = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/assignments", ['clipId' => 'vibrato.hold']);
        $this->assertMatchesContract($again, 'admin.beta.assignments.create', 422);
        $again->assertJsonValidationErrors('clipId');
        $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/assignments", ['clipId' => 'vibrato.nope'])->assertJsonValidationErrors('clipId');

        $this->assertMatchesContract($this->getJson('/api/admin/beta/singers'), 'admin.beta.singers.list', 200);

        $list = $this->getJson('/api/admin/beta/clips');
        $this->assertMatchesContract($list, 'admin.beta.clips.list', 200);
        $list->assertJsonPath('data.0.status', 'assigned')->assertJsonPath('data.0.assignee.singerId', $singer->sqid());
    }

    public function test_a_clip_comes_first_on_my_parts_and_takes_only_a_ready_take(): void
    {
        $this->catalogue();
        [$singer] = $this->singerWithAlto();
        $clip = CourseClip::factory()->create(['slot' => 'vibrato.hold']);
        $assignment = BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'template_id' => null, 'part_key' => null, 'course_clip_id' => $clip->id]);

        $parts = $this->asSinger($singer)->getJson('/api/beta/parts');
        $this->assertMatchesContract($parts, 'beta.parts.list', 200);
        $parts->assertJsonPath('data.0.kind', 'clip')->assertJsonPath('data.0.clip.id', 'vibrato.hold')->assertJsonPath('data.0.recordable', true)
            ->assertJsonPath('data.1.kind', 'part')->assertJsonPath('data.1.clip', null);

        $wav = Wav::tone(1.0);
        $take = ['assignmentId' => $assignment->sqid(), 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'latencyFrames' => 960, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav), 'device' => 'Mac, Chrome', 'mic' => 'Wired', 'scores' => ['pitchPercent' => 40]];

        $notReady = $this->asSinger($singer)->postJson('/api/beta/submissions', $take);
        $this->assertMatchesContract($notReady, 'beta.submissions.create', 422);
        $notReady->assertJsonValidationErrors('scores');

        $created = $this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'scores' => ['verdict' => 'ready']]);
        $this->assertMatchesContract($created, 'beta.submissions.create', 201);
        $created->assertJsonPath('data.templateVersion', null);

        // Retired from the courses: kept, but no longer recordable.
        $clip->forceFill(['retired_at' => now()])->save();
        $this->asSinger($singer)->getJson('/api/beta/parts')->assertJsonPath('data.0.recordable', false);
        $this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'scores' => ['verdict' => 'ready']])->assertForbidden();
    }

    public function test_accepting_a_clip_publishes_it_for_the_course_credited_and_forgetting_the_singer_takes_it_down(): void
    {
        [$singer, $assignment, $clip] = $this->singerWithClip();
        $singer->forceFill(['user_id' => User::factory()->create(['name' => 'Sarah K.'])->id])->save();
        $wav = Wav::tone(2.0);
        $submission = BetaSubmission::factory()->create([
            'beta_assignment_id' => $assignment->id,
            'template_version_id' => null,
            'part_key' => null,
            'frames' => intdiv(strlen($wav) - 44, 2),
            'bytes' => strlen($wav),
            'sha256' => hash('sha256', $wav),
            'loudness_dbfs' => -13.5,
            'peaks' => [0.3, 0.3],
        ]);
        Storage::disk('private')->put($submission->master_key, $wav);
        $this->actingAsAdmin();

        $shown = $this->getJson("/api/admin/beta/submissions/{$submission->sqid()}");
        $this->assertMatchesContract($shown, 'admin.beta.submissions.show', 200);
        $shown->assertJsonPath('data.kind', 'clip');

        $accepted = $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/accept");
        $this->assertMatchesContract($accepted, 'admin.beta.submissions.accept', 200);

        $clip->refresh();
        $this->assertNotNull($clip->media_key);
        $this->assertSame($submission->id, $clip->beta_submission_id);
        Storage::disk('media')->assertExists((string) $clip->media_key);

        $list = $this->getJson('/api/courses/vibrato/clips');
        $this->assertMatchesContract($list, 'courses.clips.list', 200);
        $list->assertJsonPath('data.0.slot', 'vibrato.hold')->assertJsonPath('data.0.credit', 'Sarah K.');
        $this->getJson('/api/courses/counterpoint/clips')->assertExactJson(['data' => []]);
        $this->getJson('/api/admin/beta/clips')->assertJsonPath('data.0.status', 'published');
        $this->asSinger($singer)->getJson('/api/beta/parts')->assertJsonPath('data.0.status', 'guide');

        app(ForgetSinger::class)->handle($singer->fresh() ?? $singer, null);
        $this->assertNull($clip->refresh()->media_key);
        $this->getJson('/api/courses/vibrato/clips')->assertExactJson(['data' => []]);
    }

    public function test_a_clip_left_unrecorded_answers_no_clips(): void
    {
        $this->singerWithClip();

        $this->getJson('/api/courses/vibrato/clips')->assertOk()->assertExactJson(['data' => []]);
    }
}
