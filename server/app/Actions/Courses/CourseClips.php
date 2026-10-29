<?php

declare(strict_types=1);

namespace App\Actions\Courses;

use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\CourseClip;
use Illuminate\Support\Facades\Storage;

/**
 * The courses' real-voice clips (Hillel: "re-use the singer feature in admin to request singers to
 * record these missing parts"): the courses define them in web/course/plus/clips.json, each by a
 * slot id that never changes; the templates sync loads them; a beta singer records one like a part;
 * accepting it publishes it to the CDN, credited, and a course step plays it from
 * courses.clips.list (else its own fallback).
 */
final class CourseClips
{
    /**
     * Loads clips.json: new slots made, changed briefs updated, slots gone from the file retired
     * (kept for what was recorded). A missing file is no clips. Answers the problems, by slot.
     *
     * @return list<string>
     */
    public function sync(): array
    {
        $path = (string) config('templates.clips');

        if (! is_file($path)) {
            return [];
        }

        $file = json_decode((string) file_get_contents($path), true);

        if (! is_array($file) || ! is_array($file['clips'] ?? null)) {
            return ['clips.json: no "clips" list'];
        }

        $problems = [];
        $seen = [];

        foreach ($file['clips'] as $i => $clip) {
            $problem = self::problem($clip);
            $slot = is_array($clip) && is_string($clip['id'] ?? null) ? $clip['id'] : "#{$i}";

            if ($problem === null && isset($seen[$slot])) {
                $problem = 'the id is used twice';
            }

            if ($problem !== null) {
                $problems[] = "clips.json {$slot}: {$problem}";

                continue;
            }

            /** @var array{id: string, course: string, lesson?: string, step?: int, sing: array<string, string>, pitch?: array<string, int>, seconds: int|float, style?: string, voices?: string|list<string>, used?: list<string>} $clip */
            $seen[$slot] = true;
            $row = CourseClip::query()->where('slot', $slot)->first() ?? new CourseClip;
            $row->forceFill([
                'slot' => $slot,
                'course_id' => $clip['course'],
                'lesson_id' => $clip['lesson'] ?? ($clip['used'][0] ?? null),
                'step' => $clip['step'] ?? null,
                'brief' => [
                    'sing' => $clip['sing'],
                    'pitch' => $clip['pitch'] ?? null,
                    'seconds' => (float) $clip['seconds'],
                    'style' => $clip['style'] ?? null,
                    'voices' => $clip['voices'] ?? 'any',
                    'used' => array_values($clip['used'] ?? []),
                ],
                'retired_at' => null,
            ])->save();
        }

        CourseClip::query()->whereNotIn('slot', array_keys($seen))->whereNull('retired_at')->update(['retired_at' => now()]);

        return $problems;
    }

    /**
     * What's wrong with one entry of clips.json, or null.
     */
    private static function problem(mixed $clip): ?string
    {
        $pitch = is_array($clip) ? ($clip['pitch'] ?? null) : null;

        return match (true) {
            ! is_array($clip) => 'not an object',
            ! is_string($clip['id'] ?? null) || preg_match('/^[a-z0-9-]+\.[a-z0-9-]+$/', $clip['id']) !== 1 => 'the id must be "<course>.<name>", lower case',
            ! is_string($clip['course'] ?? null) || ! str_starts_with($clip['id'], $clip['course'].'.') => 'the course must be the id\'s first part',
            ! is_array($clip['sing'] ?? null) || ! is_string($clip['sing']['en'] ?? null) || trim($clip['sing']['en']) === '' => 'it needs sing.en',
            ! is_numeric($clip['seconds'] ?? null) || (float) $clip['seconds'] <= 0 || (float) $clip['seconds'] > 120 => 'seconds must be more than 0 and at most 120',
            $pitch !== null && ! (is_array($pitch) && (is_int($pitch['step'] ?? null) || (is_int($pitch['low'] ?? null) && is_int($pitch['high'] ?? null) && $pitch['low'] <= $pitch['high']))) => 'pitch must be { "step" } or { "low", "high" }',
            isset($clip['voices']) && $clip['voices'] !== 'any' && ! (is_array($clip['voices']) && array_filter($clip['voices'], 'is_string') === $clip['voices']) => 'voices must be "any" or a list',
            default => null,
        };
    }

    /**
     * courses.clips.list: a course's published clips, for its steps.
     *
     * @return list<array{slot: string, url: string, durationSeconds: float, credit: string|null, creditHandle: string|null}>
     */
    public function published(string $courseId): array
    {
        return array_values(CourseClip::query()->with('submission.assignment.singer.user')->where('course_id', $courseId)->whereNotNull('media_key')->orderBy('slot')->get()
            ->map(static function (CourseClip $clip): array {
                $singer = $clip->submission?->assignment->singer;

                return [
                    'slot' => $clip->slot,
                    'url' => Storage::disk('media')->url((string) $clip->media_key),
                    'durationSeconds' => round((int) $clip->frames / max(1, (int) $clip->sample_rate), 2),
                    'credit' => $singer instanceof BetaSinger ? $singer->credit() : null,
                    'creditHandle' => $singer instanceof BetaSinger ? $singer->creditHandle() : null,
                ];
            })->all());
    }

    /**
     * admin.beta.clips.list: every clip, current ones first, with its status and who has it.
     *
     * @return list<array<string, mixed>>
     */
    public function forAdmin(): array
    {
        $assignments = BetaAssignment::query()->with(['singer', 'latestSubmission'])->whereNotNull('course_clip_id')->get()->groupBy('course_clip_id');

        return array_values(CourseClip::query()->with('submission.assignment.singer.user')->orderByRaw('retired_at is not null')->orderBy('course_id')->orderBy('slot')->get()
            ->map(static function (CourseClip $clip) use ($assignments): array {
                /** @var BetaAssignment|null $latest the newest assignment of it */
                $latest = $assignments->get($clip->id)?->sortByDesc('id')->first();
                $sent = $latest?->latestSubmission !== null;
                $singer = $clip->submission?->assignment->singer;

                return [
                    ...self::brief($clip),
                    'retired' => $clip->retired_at !== null,
                    'status' => match (true) {
                        $clip->media_key !== null => 'published',
                        $sent => 'submitted',
                        $latest !== null => 'assigned',
                        default => 'open',
                    },
                    'assignee' => $latest !== null ? ['singerId' => $latest->singer->sqid(), 'name' => $latest->singer->name] : null,
                    'published' => $clip->media_key !== null ? [
                        'url' => Storage::disk('media')->url($clip->media_key),
                        'durationSeconds' => round((int) $clip->frames / max(1, (int) $clip->sample_rate), 2),
                        'credit' => $singer instanceof BetaSinger ? $singer->credit() : null,
                    ] : null,
                ];
            })->all());
    }

    /**
     * A course's name, from its catalogue entry (web/course/plus/courses.json), else its id.
     */
    public static function courseTitle(string $courseId): string
    {
        static $titles = null;

        if ($titles === null) {
            $path = dirname(base_path()).'/web/course/plus/courses.json';
            $list = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $titles = [];

            foreach (is_array($list) ? (array) ($list['courses'] ?? []) : [] as $course) {
                if (is_array($course) && is_string($course['id'] ?? null)) {
                    $titles[$course['id']] = is_string($course['title']['en'] ?? null) ? $course['title']['en'] : $course['id'];
                }
            }
        }

        return $titles[$courseId] ?? $courseId;
    }

    /**
     * A clip's brief as the singer and the admin see it.
     *
     * @return array<string, mixed>
     */
    public static function brief(CourseClip $clip): array
    {
        return [
            'id' => $clip->slot,
            'courseId' => $clip->course_id,
            'lessonId' => $clip->lesson_id,
            'step' => $clip->step,
            'sing' => (string) ($clip->brief['sing'][app()->getLocale()] ?? $clip->brief['sing']['en'] ?? ''),
            'pitch' => $clip->brief['pitch'] ?? null,
            'seconds' => (float) $clip->brief['seconds'],
            'style' => $clip->brief['style'] ?? null,
            'voices' => $clip->brief['voices'] ?? 'any',
        ];
    }
}
