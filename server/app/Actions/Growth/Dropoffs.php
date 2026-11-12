<?php

declare(strict_types=1);

namespace App\Actions\Growth;

use App\Actions\Counts\Funnel;
use App\Models\DropoffReport;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The weekly drop-off report (docs/retention-plan.md, "Detect"; the coordinator, 2026-11-11): for each path singers
 * walk (the funnel, the free course lesson by lesson, and every lesson step by step), each step's pass-through (how
 * many of those who reached it reached the next) over the last seven whole days, beside the same over the four weeks
 * before and the median of its neighbours in the path. A step that too few reached is "too few to tell", never a drop.
 * The worst are ranked by singers lost against the neighbours' median (a mild drop many meet outranks a severe one two
 * met), and apart from them, the biggest falls on the weeks before. Anonymous daily totals only (usage_counts).
 */
final class Dropoffs
{
    /** The funnel's main line, in order: a device's firsts (config counts.funnel), arrivals as one step. */
    private const FUNNEL = [
        'arrived' => Funnel::ARRIVALS,
        'mic' => ['f_mic'],
        'note' => ['f_note'],
        'lesson 1' => ['f_lesson1'],
        'lesson 2' => ['f_lesson2'],
        'lesson 3' => ['f_lesson3'],
        'unit 1' => ['f_unit1'],
        'day 2' => ['f_day2'],
        'week 1' => ['f_week1'],
    ];

    /** How many are listed as the worst, and as the biggest falls. */
    private const TOP = 3;

    /**
     * The report for the seven days ending on $weekEnd (report timezone), kept as that week's snapshot.
     */
    public function snapshot(Carbon $weekEnd): DropoffReport
    {
        $data = $this->build($weekEnd);

        return DropoffReport::query()->updateOrCreate(['week_end' => $weekEnd->toDateString()], ['data' => $data]);
    }

    /**
     * The last whole week's end: yesterday in the report timezone.
     */
    public static function lastWeekEnd(): Carbon
    {
        return ReportDay::now()->startOfDay()->subDay();
    }

    /**
     * @return array{weekStart: string, weekEnd: string, floor: int, margin: float, paths: list<array<string, mixed>>, worst: list<array<string, mixed>>, falls: list<array<string, mixed>>}
     */
    public function build(Carbon $weekEnd): array
    {
        $end = $weekEnd->copy()->startOfDay();
        $start = $end->copy()->subDays(6);
        $beforeEnd = $start->copy()->subDay();
        $beforeStart = $beforeEnd->copy()->subDays(27);
        $floor = (int) config('counts.dropoffs.floor', 30);
        $margin = (float) config('counts.dropoffs.margin', 0.15);

        $week = self::totals($start, $end);
        $before = self::totals($beforeStart, $beforeEnd);
        // The long window, the last 28 whole days to the week's end: at today's traffic a deep step can be read only
        // over a month (retention, 2026-11-11). Shown beside the week, never ranked.
        $month = self::totals($end->copy()->subDays(27), $end);
        $paths = [];

        foreach ($this->paths($month) as $path) {
            $paths[] = self::read($path, $week, $before, $month, $floor, $margin);
        }

        $steps = [];
        foreach ($paths as $path) {
            foreach ($path['steps'] as $step) {
                $steps[] = ['path' => $path['path'], 'pathLabel' => $path['label'], ...$step];
            }
        }

        $worst = array_values(array_filter($steps, static fn (array $s): bool => $s['drop'] && $s['lost'] > 0));
        usort($worst, static fn (array $a, array $b): int => [$b['lost'], $b['entered']] <=> [$a['lost'], $a['entered']]);
        $falls = array_values(array_filter($steps, static fn (array $s): bool => ! $s['tooFew'] && $s['fall'] !== null && $s['fall'] > $margin));
        usort($falls, static fn (array $a, array $b): int => $b['fall'] <=> $a['fall']);

        return [
            'weekStart' => $start->toDateString(),
            'weekEnd' => $end->toDateString(),
            'floor' => $floor,
            'margin' => $margin,
            'paths' => $paths,
            'worst' => array_slice($worst, 0, self::TOP),
            'falls' => array_slice($falls, 0, self::TOP),
        ];
    }

    /**
     * Every path, in order: the funnel, the free course by lesson, each free lesson by step, and each other course's
     * lesson by step (only a lesson someone started in the last 28 days: the rest have nothing to read).
     *
     * @param  array<string, int>  $month
     * @return list<array{path: string, label: string, steps: list<array{step: string, label: string, kinds: list<string>}>}>
     */
    private function paths(array $month): array
    {
        $paths = [['path' => 'funnel', 'label' => 'First visit to week one', 'steps' => array_map(static fn (string $label, array $kinds): array => ['step' => $kinds[0], 'label' => $label, 'kinds' => $kinds], array_keys(self::FUNNEL), self::FUNNEL)]];

        $free = self::lessons(dirname(base_path()).'/web/course/course.json');
        $course = [];
        foreach ($free as $n => [$id, $title]) {
            $course[] = ['step' => 'fl_s:'.$id, 'label' => 'Lesson '.($n + 1).', '.$title.': started', 'kinds' => ['fl_s:'.$id]];
            $course[] = ['step' => 'fl_d:'.$id, 'label' => 'Lesson '.($n + 1).', '.$title.': finished', 'kinds' => ['fl_d:'.$id]];
        }
        $paths[] = ['path' => 'free-course', 'label' => 'The free course, lesson by lesson', 'steps' => $course];

        foreach ($free as $n => [$id, $title, $steps]) {
            $paths[] = self::lessonPath('lesson:'.$id, 'Lesson '.($n + 1).', '.$title, 'fl_s:'.$id, 'fs_r:'.$id, 'fl_d:'.$id, $steps);
        }

        foreach (self::otherLessons() as [$id, $title, $steps]) {
            if (($month['cl_s:'.$id] ?? 0) > 0) {
                $paths[] = self::lessonPath('lesson:'.$id, $title, 'cl_s:'.$id, 'cs_r:'.$id, 'cl_d:'.$id, $steps);
            }
        }

        // A unit's end offering a song (docs/retention-plan.md, section 8, row 8), each place its own path: the offer
        // shown, its button tapped, a take kept from that visit.
        foreach ((array) config('counts.song_offers') as $place) {
            $place = (string) $place;
            $paths[] = ['path' => 'offer:'.$place, 'label' => $place === 'wall' ? 'The song offered at the wall' : 'The song offered after unit '.substr($place, 1), 'steps' => [
                ['step' => 'us_shown:'.$place, 'label' => 'shown', 'kinds' => ['us_shown:'.$place]],
                ['step' => 'us_tap:'.$place, 'label' => 'tapped', 'kinds' => ['us_tap:'.$place]],
                ['step' => 'us_kept:'.$place, 'label' => 'a take kept', 'kinds' => ['us_kept:'.$place]],
            ]];
        }

        return $paths;
    }

    /**
     * A lesson, step by step: started, each step reached, finished.
     *
     * @return array{path: string, label: string, steps: list<array{step: string, label: string, kinds: list<string>}>}
     */
    private static function lessonPath(string $path, string $label, string $started, string $reached, string $finished, int $steps): array
    {
        $list = [['step' => $started, 'label' => 'started', 'kinds' => [$started]]];
        for ($i = 0; $i < $steps; $i++) {
            $list[] = ['step' => $reached.'.'.$i, 'label' => 'step '.($i + 1), 'kinds' => [$reached.'.'.$i]];
        }
        $list[] = ['step' => $finished, 'label' => 'finished', 'kinds' => [$finished]];

        return ['path' => $path, 'label' => $label, 'steps' => $list];
    }

    /**
     * Each step's pass-through, its weeks before, its neighbours' median, and whether it's a drop.
     *
     * @param  array{path: string, label: string, steps: list<array{step: string, label: string, kinds: list<string>}>}  $path
     * @param  array<string, int>  $week
     * @param  array<string, int>  $before
     * @param  array<string, int>  $month
     * @return array{path: string, label: string, steps: list<array<string, mixed>>}
     */
    private static function read(array $path, array $week, array $before, array $month, int $floor, float $margin): array
    {
        $sum = static fn (array $totals, array $kinds): int => array_sum(array_map(static fn (string $k): int => $totals[$k] ?? 0, $kinds));
        $steps = $path['steps'];
        $rows = [];

        // Every step but the last leads on to the next.
        for ($i = 0; $i < count($steps) - 1; $i++) {
            $entered = $sum($week, $steps[$i]['kinds']);
            $passed = $sum($week, $steps[$i + 1]['kinds']);
            $enteredBefore = $sum($before, $steps[$i]['kinds']);
            $rows[] = [
                'step' => $steps[$i]['step'],
                'label' => $steps[$i]['label'].' to '.$steps[$i + 1]['label'],
                'entered' => $entered,
                'passed' => $passed,
                'passThrough' => $entered > 0 ? round(min(1, $passed / $entered), 3) : null,
                // Read over the four weeks before only with as many as a week's floor in each.
                'before' => $enteredBefore >= $floor * 4 ? round(min(1, $sum($before, $steps[$i + 1]['kinds']) / $enteredBefore), 3) : null,
                'tooFew' => $entered < $floor,
                'month' => self::window($month, $steps[$i]['kinds'], $steps[$i + 1]['kinds'], $floor, $sum),
            ];
        }

        $lesson = str_starts_with($path['path'], 'lesson:');

        foreach ($rows as $i => $row) {
            // The neighbours: a lesson's other steps; elsewhere the two steps either side. Only those with enough.
            $around = array_filter($rows, static fn (array $other, int $j): bool => $j !== $i && ! $other['tooFew'] && $other['passThrough'] !== null && ($lesson || abs($j - $i) <= 2), ARRAY_FILTER_USE_BOTH);
            $median = self::median(array_map(static fn (array $other): float => (float) $other['passThrough'], array_values($around)));
            $p = $row['passThrough'];
            $usable = ! $row['tooFew'] && $p !== null;
            $belowNeighbours = $usable && $median !== null && $p < $median - $margin;
            $fall = $usable && $row['before'] !== null ? round($row['before'] - $p, 3) : null;

            $rows[$i] += [
                'neighbours' => $median,
                'fall' => $fall,
                'drop' => $belowNeighbours || ($fall !== null && $fall > $margin),
                // How many more would have gone on had this step passed as its neighbours do.
                'lost' => $belowNeighbours ? (int) round($row['entered'] * ($median - $p)) : 0,
            ];
        }

        return ['path' => $path['path'], 'label' => $path['label'], 'steps' => $rows];
    }

    /**
     * A step over the long window: entered, passed, its pass-through, and whether too few reached it even then.
     *
     * @param  array<string, int>  $totals
     * @param  list<string>  $from
     * @param  list<string>  $to
     * @param  callable(array<string, int>, list<string>): int  $sum
     * @return array{entered: int, passed: int, passThrough: float|null, tooFew: bool}
     */
    private static function window(array $totals, array $from, array $to, int $floor, callable $sum): array
    {
        $entered = $sum($totals, $from);
        $passed = $sum($totals, $to);

        return ['entered' => $entered, 'passed' => $passed, 'passThrough' => $entered > 0 ? round(min(1, $passed / $entered), 3) : null, 'tooFew' => $entered < $floor];
    }

    /**
     * @param  list<float>  $values
     */
    private static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $mid = intdiv(count($values), 2);

        return round(count($values) % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2, 3);
    }

    /**
     * Each kind's total (guests and accounts) over the days.
     *
     * @return array<string, int>
     */
    private static function totals(Carbon $from, Carbon $to): array
    {
        return DB::table('usage_counts')->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('kind, sum(count) as n')->groupBy('kind')->pluck('n', 'kind')
            ->map(static fn (mixed $n): int => (int) $n)->all();
    }

    /**
     * A course file's lessons in order: id, English title and step count (its longest form).
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private static function lessons(string $file): array
    {
        $course = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $out = [];

        foreach (is_array($course) ? (array) ($course['units'] ?? []) : [] as $unit) {
            foreach (is_array($unit) ? (array) ($unit['lessons'] ?? []) : [] as $lesson) {
                if (! is_array($lesson) || ! is_string($lesson['id'] ?? null)) {
                    continue;
                }

                $steps = count((array) ($lesson['steps'] ?? []));
                foreach ((array) ($lesson['variants'] ?? []) as $variant) {
                    $steps = max($steps, is_array($variant) ? count((array) ($variant['steps'] ?? [])) : 0);
                }

                $title = $lesson['title'] ?? $lesson['id'];
                $out[] = [$lesson['id'], is_array($title) ? (string) ($title['en'] ?? $lesson['id']) : (string) $title, $steps];
            }
        }

        return $out;
    }

    /**
     * Every Plus and song course's lessons, each titled with its course.
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private static function otherLessons(): array
    {
        $root = dirname(base_path()).'/web/course';
        $out = [];

        foreach (['plus/courses.json' => 'plus', 'songs/index.json' => 'songs'] as $list => $folder) {
            $index = is_file($root.'/'.$list) ? json_decode((string) file_get_contents($root.'/'.$list), true) : null;

            foreach (is_array($index) ? (array) ($index['courses'] ?? []) : [] as $course) {
                if (! is_array($course) || ! is_string($course['file'] ?? null) || str_contains($course['file'], '..')) {
                    continue;
                }

                $name = $course['title'] ?? $course['id'] ?? '';
                $name = is_array($name) ? (string) ($name['en'] ?? '') : (string) $name;

                foreach (self::lessons($root.'/'.$folder.'/'.$course['file']) as $n => [$id, $title, $steps]) {
                    $out[] = [$id, trim($name.', lesson '.($n + 1).', '.$title, ', '), $steps];
                }
            }
        }

        return $out;
    }
}
