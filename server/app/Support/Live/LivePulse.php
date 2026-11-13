<?php

declare(strict_types=1);

namespace App\Support\Live;

use App\Actions\Counts\ActiveSingers;
use App\Actions\Counts\Growth;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * admin.pulse.show (docs/realtime-dashboard-plan.md): the slower half of Admin > Live, every minute. Active singers
 * (as admin.active.show), the last 24 hours' sign-ups (the steps each new account has reached, as Admin > People shows
 * them, and where they came from as totals only: Hillel, "Totals only"), and the day's activity counts.
 */
final class LivePulse
{
    /** Each step from the features an account has tried (users.tried, config counts.features). */
    public const STEPS = ['lesson1' => 'lesson', 'wholeSong' => 'whole', 'keptPart' => 'record', 'shared' => 'share'];

    /** The Usage page's kinds the activity row shows. */
    public const ACTIVITY = ['lesson_done', 'lesson_start', 'take_recorded', 'song_created', 'share_created', 'result_shared', 'export_done'];

    /** New accounts listed at most. */
    public const PEOPLE = 50;

    public function __construct(private readonly ActiveSingers $active, private readonly Growth $growth) {}

    /**
     * @return array{at: string, active: array{signedIn7d: int, change: int, active30d: int, weeks: list<int>}, signUps24h: array<string, mixed>, activity24h: list<array{kind: string, n: int}>}
     */
    public function show(?Carbon $at = null): array
    {
        $at ??= now();
        $active = $this->active->show();
        $weeks = array_map(static fn (array $w): int => (int) ($w['week'] ?? 0), $active['weeks']);
        $before = $weeks[count($weeks) - 2] ?? 0;

        return [
            'at' => $at->copy()->utc()->toIso8601ZuluString(),
            'active' => ['signedIn7d' => $active['week'], 'change' => $active['week'] - $before, 'active30d' => $active['month'], 'weeks' => $weeks],
            'signUps24h' => $this->signUps($at),
            'activity24h' => $this->activity($at),
        ];
    }

    /** @return array{funnel: array<string, int>, hourly: list<int>, people: list<array<string, mixed>>, sources: list<array<string, int|string>>} */
    private function signUps(Carbon $at): array
    {
        $from = $at->copy()->subDay();
        $users = User::query()->where('created_at', '>=', $from)->where('is_admin', false)->orderByDesc('created_at')->get(['id', 'name', 'tried', 'created_at']);
        $stepsOf = static function (User $user): array {
            $tried = (array) ($user->tried ?? []);

            return ['created', ...array_keys(array_filter(self::STEPS, static fn (string $feature): bool => isset($tried[$feature])))];
        };

        $funnel = ['created' => $users->count()];
        foreach (array_keys(self::STEPS) as $step) {
            $funnel[$step] = $users->filter(fn (User $u): bool => in_array($step, $stepsOf($u), true))->count();
        }

        // Each of the last 24 hours, the one so far last.
        $hourly = array_fill(0, 24, 0);
        $thisHour = $at->copy()->startOfHour();
        foreach ($users as $user) {
            $back = (int) floor($thisHour->diffInHours($user->created_at?->copy()->startOfHour() ?? $thisHour, true));
            if ($back < 24) {
                $hourly[23 - $back]++;
            }
        }

        $sources = [];
        foreach ($this->growth->sources(1)['sources'] as $row) {
            if ((int) ($row['signedUp'] ?? 0) > 0) {
                $sources[] = ['source' => (string) $row['source'], 'n' => (int) $row['signedUp'], ...(isset($row['sources']) ? ['sources' => (int) $row['sources']] : [])];
            }
        }

        return [
            'funnel' => $funnel,
            'hourly' => array_values($hourly),
            'people' => array_values($users->take(self::PEOPLE)->map(fn (User $u): array => ['id' => $u->sqid(), 'name' => (string) $u->name, 'steps' => $stepsOf($u), 'at' => $u->created_at?->utc()->toIso8601ZuluString()])->all()),
            'sources' => $sources,
        ];
    }

    /** @return list<array{kind: string, n: int}> */
    private function activity(Carbon $at): array
    {
        $totals = DB::table('usage_count_hours')->whereIn('kind', self::ACTIVITY)->where('hour', '>=', $at->copy()->subDay()->utc()->format('Y-m-d H:i:s'))
            ->selectRaw('kind, sum(count) as n')->groupBy('kind')->pluck('n', 'kind');
        $rows = array_map(static fn (string $kind): array => ['kind' => $kind, 'n' => (int) ($totals[$kind] ?? 0)], self::ACTIVITY);
        usort($rows, static fn (array $a, array $b): int => [$b['n'], $a['kind']] <=> [$a['n'], $b['kind']]);

        return $rows;
    }
}
