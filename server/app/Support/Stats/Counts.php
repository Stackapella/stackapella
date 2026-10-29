<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\LessonBooking;
use App\Models\Score;
use App\Models\Take;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What we count, from our own records only (never analytics; docs/decisions.md, L):
 * - a **finished song**: a project from a template whose parts (two or more) each have a ready
 *   take its owner sang;
 * - **singers active in a week**: signed-in people who recorded, practised (a score saved) or had
 *   a lesson in the last 7 days.
 */
final class Counts
{
    /**
     * Every finished song: the project, its owner and its template.
     *
     * @return Collection<int, object{id: int, user_id: int, template_slug: string}>
     */
    public static function finished(): Collection
    {
        /** @var Collection<int, object{id: int, user_id: int, template_slug: string}> */
        return DB::table('projects')
            ->join('parts', 'parts.project_id', '=', 'projects.id')
            ->leftJoin('takes', fn ($join) => $join->on('takes.id', '=', 'parts.current_take_id')
                ->where('takes.status', Take::READY)->whereNull('takes.binned_at')
                ->where(fn ($q) => $q->whereNull('takes.recorded_by')->orWhereColumn('takes.recorded_by', 'projects.user_id')))
            ->whereNull('projects.binned_at')->whereNotNull('projects.template_slug')->whereNotNull('parts.template_part_key')
            ->groupBy('projects.id', 'projects.user_id', 'projects.template_slug')
            ->havingRaw('count(parts.id) >= 2 and count(parts.id) = count(takes.id)')
            ->get(['projects.id', 'projects.user_id', 'projects.template_slug']);
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

    public static function activeSingers(?Carbon $since = null): int
    {
        $since ??= now()->subWeek();
        // Whoever sang the take (a friend on someone's project counts as themselves).
        $ids = Take::query()->where('created_at', '>', $since)->selectRaw('coalesce(recorded_by, user_id) as singer')->pluck('singer')
            ->merge(Score::query()->where('created_at', '>', $since)->pluck('user_id'))
            ->merge(LessonBooking::query()->whereIn('lesson_bookings.status', [LessonBooking::CONFIRMED, LessonBooking::COMPLETED])->where('lesson_bookings.starts_at', '>', $since)->where('lesson_bookings.starts_at', '<=', now())->join('engagements', 'engagements.id', '=', 'lesson_bookings.engagement_id')->pluck('engagements.singer_id'));

        return $ids->filter()->unique()->count();
    }
}
