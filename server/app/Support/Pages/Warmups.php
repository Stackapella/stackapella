<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Facades\Lang;

/**
 * The free warm-ups (/warmups, docs/seo-growth-plan.md item 8): the courses lead's list
 * (WEB_PATH's course/warmups.json, from the content repo), each entry pointing at a course step for its
 * notes. A warm-up has a page once its entry, its course step's exercise and its page words
 * (site.warmups.pages.<id>) are all there, so the sitemap never lists a page that can't play.
 */
final class Warmups
{
    /** @var array<string, array{entry: array<string, mixed>, exercise: array<string, mixed>}>|null */
    private static ?array $found = null;

    /**
     * The warm-ups with a page, in the list's order.
     *
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    /**
     * One warm-up's entry and its course step's exercise, as the player takes them; null for none.
     *
     * @return array{entry: array<string, mixed>, exercise: array<string, mixed>}|null
     */
    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Read again on the next call (tests that swap the files). */
    public static function forget(): void
    {
        self::$found = null;
    }

    /** @return array<string, array{entry: array<string, mixed>, exercise: array<string, mixed>}> */
    private static function all(): array
    {
        if (self::$found !== null) {
            return self::$found;
        }

        $found = [];
        $list = self::json('warmups.json');
        foreach ((array) ($list['warmups'] ?? []) as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null) || preg_match('/^[a-z0-9-]{1,40}$/', $entry['id']) !== 1) {
                continue;
            }
            $exercise = self::exercise((array) ($entry['from'] ?? []));
            if ($exercise !== null && is_array(Lang::get('site.warmups.pages.'.$entry['id']))) {
                $found[$entry['id']] = ['entry' => $entry, 'exercise' => $exercise];
            }
        }

        return self::$found = $found;
    }

    /**
     * A course step's exercise: the free course's (course.json) or a Plus course's file, by the Plus
     * catalogue (plus/courses.json), as js/public/tools/warmup-player.js loadWarmup() finds it.
     *
     * @param  array<mixed>  $from
     * @return array<string, mixed>|null
     */
    private static function exercise(array $from): ?array
    {
        $course = $from['course'] ?? null;
        $lesson = $from['lesson'] ?? null;
        $step = $from['step'] ?? null;
        if (! is_string($course) || ! is_string($lesson) || ! is_int($step)) {
            return null;
        }

        $file = 'course.json';
        if ($course !== 'free') {
            $plus = collect((array) (self::json('plus/courses.json')['courses'] ?? []))
                ->first(static fn (mixed $c): bool => is_array($c) && ($c['id'] ?? null) === $course);
            if (! is_array($plus) || ! is_string($plus['file'] ?? null) || str_contains($plus['file'], '..')) {
                return null;
            }
            $file = 'plus/'.$plus['file'];
        }

        foreach ((array) (self::json($file)['units'] ?? []) as $unit) {
            foreach ((array) (is_array($unit) ? ($unit['lessons'] ?? []) : []) as $found) {
                if (is_array($found) && ($found['id'] ?? null) === $lesson) {
                    $exercise = ((array) ($found['steps'] ?? []))[$step]['exercise'] ?? null;

                    return is_array($exercise) ? $exercise : null;
                }
            }
        }

        return null;
    }

    /** @return array<mixed> */
    private static function json(string $file): array
    {
        $path = rtrim((string) config('app.web_path'), '/').'/course/'.$file;
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }
}
