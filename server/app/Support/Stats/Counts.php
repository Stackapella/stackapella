<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\LessonBooking;
use App\Models\Score;
use App\Models\Take;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What we count, from our own records only (never analytics; docs/decisions.md, L):
 * - a **finished song**: a project from a template whose parts (two or more) each have a ready
 *   take its owner sang;
 * - **singers active in a week**: signed-in people who recorded, practised (a score saved) or had
 *   a lesson in the last 7 days.
 * Admins' own accounts are left out of both (Hillel: his own use isn't counted).
 */
final class Counts
{
    /**
     * Every finished song: the project, its owner, its template, and when its last part was sung.
     *
     * @return Collection<int, object{id: int, user_id: int, template_slug: string, finished_at: string|null}>
     */
    public static function finished(): Collection
    {
        /** @var Collection<int, object{id: int, user_id: int, template_slug: string, finished_at: string|null}> */
        return DB::table('projects')
            ->join('parts', 'parts.project_id', '=', 'projects.id')
            ->leftJoin('takes', fn ($join) => $join->on('takes.id', '=', 'parts.current_take_id')
                ->where('takes.status', Take::READY)->whereNull('takes.binned_at')
                ->where(fn ($q) => $q->whereNull('takes.recorded_by')->orWhereColumn('takes.recorded_by', 'projects.user_id')))
            ->whereNull('projects.binned_at')->whereNotNull('projects.template_slug')->whereNotIn('projects.user_id', self::admins())->whereNotNull('parts.template_part_key')
            ->groupBy('projects.id', 'projects.user_id', 'projects.template_slug')
            ->havingRaw('count(parts.id) >= 2 and count(parts.id) = count(takes.id)')
            ->selectRaw('projects.id, projects.user_id, projects.template_slug, max(takes.created_at) as finished_at')
            ->get();
    }

    /**
     * @return array{finished: int, secondSongs: int}
     */
    public static function songs(): array
    {
        $finished = self::finished();
        $perPerson = $finished->groupBy('user_id')->map(fn (Collection $songs): int => $songs->pluck('template_slug')->unique()->count());

        return ['finished' => $finished->count(), 'secondSongs' => $perPerson->filter(fn (int $songs): bool => $songs >= 2)->count()];
    }

    /**
     * Singers active from `$since` (exclusive) to `$until` (inclusive; now by default).
     */
    public static function activeSingers(?Carbon $since = null, ?Carbon $until = null): int
    {
        return self::activeIds($since, $until)->count();
    }

    /**
     * Who was active from `$since` (exclusive) to `$until` (inclusive), admins left out.
     *
     * @return Collection<int, int>
     */
    public static function activeIds(?Carbon $since = null, ?Carbon $until = null): Collection
    {
        // Compared with UTC timestamps, whatever zone the moments were worked out in.
        $until = ($until ?? now())->copy()->utc();
        $since = $since !== null ? $since->copy()->utc() : $until->copy()->subWeek();
        // Whoever sang the take (a friend on someone's project counts as themselves).
        // Each list distinct in the database: a busy week is thousands of takes but far fewer singers.
        $ids = Take::query()->where('created_at', '>', $since)->where('created_at', '<=', $until)->selectRaw('coalesce(recorded_by, user_id) as singer')->distinct()->pluck('singer')
            ->merge(Score::query()->where('created_at', '>', $since)->where('created_at', '<=', $until)->distinct()->pluck('user_id'))
            ->merge(LessonBooking::query()->whereIn('lesson_bookings.status', [LessonBooking::CONFIRMED, LessonBooking::COMPLETED])->where('lesson_bookings.starts_at', '>', $since)->where('lesson_bookings.starts_at', '<=', $until)->join('engagements', 'engagements.id', '=', 'lesson_bookings.engagement_id')->pluck('engagements.singer_id'));

        return $ids->filter()->map(fn (mixed $id): int => (int) $id)->unique()->diff(self::admins())->values();
    }

    /**
     * The admins' own accounts, left out of what counts people.
     *
     * @return list<int>
     */
    public static function admins(): array
    {
        // Once a request: several counts ask.
        return once(fn (): array => array_values(User::query()->where('is_admin', true)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()));
    }
}
