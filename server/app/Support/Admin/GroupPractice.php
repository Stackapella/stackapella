<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupSong;
use App\Models\Score;
use App\Models\Take;
use App\Support\Stats\ReportDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Groups (docs/choir-tracks-plan.md, "How we'll know"): each choir group's practice of its
 * own songs, week by week, as totals only: how many of its members sang one of them (a take or a
 * scored run), and how many takes and runs. Never who: no row stands for a member.
 */
final class GroupPractice
{
    /** Weeks shown, each Monday to Sunday in the report timezone, the current one last. */
    public const WEEKS = 8;

    /** Groups shown, the most active first. */
    public const LIMIT = 100;

    /**
     * @return array{groups: list<array<string, mixed>>, total: int}
     */
    public function show(): array
    {
        $weeks = $this->weeks();
        $from = $weeks[0]->copy()->utc();
        $total = Group::query()->count();
        $groups = Group::query()->with('director')->withCount([
            'members as singers_count' => fn ($query) => $query->where('role', GroupMember::MEMBER),
            'songs',
        ])->latest('id')->limit(self::LIMIT * 5)->get();

        $members = GroupMember::query()->whereIn('group_id', $groups->pluck('id'))->where('role', GroupMember::MEMBER)->get(['group_id', 'user_id'])->groupBy('group_id');
        $songs = GroupSong::query()->with('template:id,slug')->whereIn('group_id', $groups->pluck('id'))->get(['group_id', 'template_id'])
            ->groupBy('group_id')->map(fn (Collection $rows): array => $rows->map(fn (GroupSong $song): string => $song->template->slug)->unique()->values()->all());

        $userIds = $members->flatten()->pluck('user_id')->unique()->values();
        $slugs = $songs->flatten()->unique()->values();
        // Each sung moment, as (user, song, week): takes by whoever sang them, scored runs by their owner.
        $takes = $userIds->isEmpty() || $slugs->isEmpty() ? collect() : Take::query()->join('projects', 'projects.id', '=', 'takes.project_id')
            ->where('takes.status', Take::READY)->whereNull('takes.binned_at')->where('takes.created_at', '>=', $from)
            ->whereIn('projects.template_slug', $slugs)->whereIn(DB::raw('coalesce(takes.recorded_by, takes.user_id)'), $userIds)
            ->toBase()->get([DB::raw('coalesce(takes.recorded_by, takes.user_id) as singer'), 'projects.template_slug as slug', 'takes.created_at as at'])->map(self::moment(...));
        $scores = $userIds->isEmpty() || $slugs->isEmpty() ? collect() : Score::query()->where('created_at', '>=', $from)
            ->whereIn('template_slug', $slugs)->whereIn('user_id', $userIds)
            ->toBase()->get(['user_id as singer', 'template_slug as slug', 'created_at as at'])->map(self::moment(...));

        $weekOf = function (string $at) use ($weeks): ?int {
            $moment = Carbon::parse($at, 'UTC');

            for ($i = self::WEEKS - 1; $i >= 0; $i--) {
                if ($moment->gte($weeks[$i])) {
                    return $i;
                }
            }

            return null;
        };

        $rows = $groups->map(function (Group $group) use ($members, $songs, $takes, $scores, $weeks, $weekOf): array {
            $theirs = $members->get($group->id, collect())->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->flip();
            $own = array_flip($songs->get($group->id, []));
            $mine = fn (array $row): bool => $theirs->has($row['singer']) && isset($own[$row['slug']]);
            $byWeek = array_fill(0, self::WEEKS, ['singers' => [], 'takes' => 0, 'scores' => 0]);

            foreach (['takes' => $takes, 'scores' => $scores] as $kind => $list) {
                foreach ($list->filter($mine) as $row) {
                    $i = $weekOf($row['at']);

                    if ($i !== null) {
                        $byWeek[$i][$kind]++;
                        $byWeek[$i]['singers'][$row['singer']] = true;
                    }
                }
            }

            return [
                'id' => $group->sqid(),
                'name' => $group->name,
                'director' => ['id' => $group->director->sqid(), 'name' => $group->director->name],
                'members' => (int) $group->getAttribute('singers_count'),
                'songs' => (int) $group->getAttribute('songs_count'),
                'createdAt' => $group->created_at?->utc()->toIso8601ZuluString(),
                'weeks' => array_map(fn (int $i): array => ['week' => $weeks[$i]->toDateString(), 'singers' => count($byWeek[$i]['singers']), 'takes' => $byWeek[$i]['takes'], 'scores' => $byWeek[$i]['scores']], range(0, self::WEEKS - 1)),
            ];
        });

        $recent = fn (array $row): int => $row['weeks'][self::WEEKS - 1]['singers'] + $row['weeks'][self::WEEKS - 2]['singers'];
        $sorted = $rows->sort(fn (array $a, array $b): int => [$recent($b), $b['createdAt']] <=> [$recent($a), $a['createdAt']])->take(self::LIMIT)->all();

        return ['groups' => array_values($sorted), 'total' => $total];
    }

    /**
     * One sung moment from a query row: who, which song, and when (UTC).
     *
     * @return array{singer: int, slug: string, at: string}
     */
    private static function moment(object $row): array
    {
        $fields = (array) $row;

        return ['singer' => (int) ($fields['singer'] ?? 0), 'slug' => (string) ($fields['slug'] ?? ''), 'at' => (string) ($fields['at'] ?? '')];
    }

    /**
     * Each shown week's Monday, at its start in the report timezone, oldest first.
     *
     * @return list<Carbon>
     */
    private function weeks(): array
    {
        $monday = ReportDay::now()->startOfWeek(Carbon::MONDAY);

        return array_map(fn (int $back): Carbon => $monday->copy()->subWeeks($back), range(self::WEEKS - 1, 0));
    }
}
