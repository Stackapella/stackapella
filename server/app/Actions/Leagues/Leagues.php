<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\Exceptions\Refusal;
use App\Models\Follow;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Trust\TrustLevel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leagues and friends' leaderboards (N2; docs/vocal-coaching-plan.md, section 8): opt-in weekly
 * leagues of about 30 singers in a tier, ranked by XP (effort, never accuracy); each Monday the
 * top 5 move up and nobody moves down. XP is what the app syncs in the course progress
 * (`progress.xp.days`), each day capped at 300 as the app caps it; a day over the cap, or in the
 * future, is clamped and flagged for Hillel. Public leagues are 18 and over (the Children's Code);
 * the Friends tab is everyone's.
 */
final class Leagues
{
    /** The app's own daily cap (web/js/course.js, DAILY_XP_CAP). */
    public const DAILY_XP_CAP = 300;

    public function __construct(private readonly Flags $flags, private readonly Gallery $gallery, private readonly TrustLevel $trust) {}

    public function isOpen(): bool
    {
        return $this->flags->on('leagues');
    }

    public static function week(?Carbon $at = null): Carbon
    {
        return ($at ?? now())->copy()->utc()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * A person's capped XP in a week, and whether anything was off (over the cap, or future days).
     *
     * @return array{xp: int, total: int, flagged: bool}
     */
    public static function xpIn(?UserLearning $learning, Carbon $week): array
    {
        $days = (array) (($learning?->progress['xp'] ?? [])['days'] ?? []);
        $from = $week->toDateString();
        $to = $week->copy()->addDays(6)->toDateString();
        $tomorrow = now()->utc()->addDay()->toDateString();
        $xp = 0;
        $total = 0;
        $flagged = false;

        foreach ($days as $day => $amount) {
            if (! is_string($day) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1 || ! is_numeric($amount)) {
                continue;
            }

            $capped = max(0, min(self::DAILY_XP_CAP, (int) $amount));
            $flagged = $flagged || (int) $amount > self::DAILY_XP_CAP || ($day > $tomorrow && (int) $amount > 0);

            if ($day > $tomorrow) {
                continue;
            }

            $total += $capped;

            if ($day >= $from && $day <= $to) {
                $xp += $capped;
            }
        }

        return ['xp' => $xp, 'total' => $total, 'flagged' => $flagged];
    }

    /**
     * The voice level for a total, as the app's levelFor().
     */
    public static function level(int $total): int
    {
        $level = 1;

        while ($total >= 50 * $level * ($level + 1)) {
            $level++;
        }

        return $level;
    }

    /**
     * @return array<string, mixed>
     */
    public function join(User $user): array
    {
        if (! $this->isOpen()) {
            throw new Refusal(409, 'leagues_closed');
        }

        Gallery::ensureAdult($user);

        if ($user->handle === null) {
            throw ValidationException::withMessages(['handle' => __('validation.handle_for_profile')]);
        }

        if ($this->trust->of($user) < TrustLevel::VERIFIED) {
            throw new Refusal(403, 'email_unverified');
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['league_joined_at' => $user->league_joined_at ?? now()])->save();
            $this->place($user, self::week());
        });

        return $this->current($user);
    }

    public function leave(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['league_joined_at' => null])->save();
            LeagueMember::query()->where('user_id', $user->id)->whereIn('league_id', League::query()->where('week', self::week()->toDateString())->select('id'))->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function current(User $user): array
    {
        $week = self::week();
        $member = LeagueMember::query()->with('league')->where('user_id', $user->id)->whereHas('league', fn ($q) => $q->where('week', $week->toDateString()))->first();
        $base = [
            'joined' => $user->league_joined_at !== null,
            'week' => $week->toDateString(),
            'endsAt' => $week->copy()->addWeek()->utc()->toIso8601ZuluString(),
            // From 0 (the first tier) to tiers - 1 (the top).
            'tier' => $user->league_tier - 1,
            'tiers' => League::TOP_TIER,
            'promote' => League::PROMOTED,
            'lastWeek' => $this->lastWeek($user, $week),
        ];

        if ($member === null || $user->league_joined_at === null) {
            return [...$base, 'members' => []];
        }

        $people = array_values(array_filter(LeagueMember::query()->with('user')->where('league_id', $member->league_id)->get()->map(fn (LeagueMember $m): ?User => $m->user)->all()));
        $ranked = $this->ranked($people, $user, $week);

        // The top 5 with any XP now move up on Monday (not from the top tier).
        return [...$base, 'members' => array_map(static fn (array $row): array => [...$row, 'movingUp' => $row['rank'] <= League::PROMOTED && $row['xp'] > 0 && $user->league_tier < League::TOP_TIER], $ranked)];
    }

    /**
     * @return array<string, mixed>
     */
    public function friends(User $user): array
    {
        $week = self::week();
        $followed = User::query()->whereIn('id', Follow::query()->where('follower_id', $user->id)->select('followee_id'))->whereNotNull('league_joined_at')->get()->all();

        return ['week' => $week->toDateString(), 'members' => $this->ranked([$user, ...$followed], $user, $week)];
    }

    /**
     * How last week went, for "You moved up" once on Monday: its tier (from 0), the rank, and
     * whether they moved up.
     *
     * @return array{tier: int, rank: int, movedUp: bool}|null
     */
    private function lastWeek(User $user, Carbon $week): ?array
    {
        $member = LeagueMember::query()->with('league')->where('user_id', $user->id)
            ->whereHas('league', fn ($q) => $q->where('week', $week->copy()->subWeek()->toDateString())->whereNotNull('closed_at'))->first();

        if ($member === null || $member->league === null) {
            return null;
        }

        $ahead = LeagueMember::query()->where('league_id', $member->league_id)->where('xp', '>', $member->xp)->count();

        return ['tier' => $member->league->tier - 1, 'rank' => $ahead + 1, 'movedUp' => $member->promoted];
    }

    /**
     * Each Monday: last week's leagues close (the top 5 with any XP move up a tier, nobody down),
     * and this week's are formed by tier, then by voice level, about 30 to a league.
     */
    public function weekly(?Carbon $at = null): int
    {
        $week = self::week($at);
        $closed = 0;

        foreach (League::query()->with('members.user')->where('week', '<', $week->toDateString())->whereNull('closed_at')->get() as $league) {
            DB::transaction(function () use ($league): void {
                $learning = UserLearning::query()->whereIn('user_id', $league->members->pluck('user_id'))->get()->keyBy('user_id');

                foreach ($league->members as $member) {
                    $counted = self::xpIn($learning->get($member->user_id), $league->week);
                    $member->forceFill(['xp' => $counted['xp'], 'flagged_at' => $counted['flagged'] ? ($member->flagged_at ?? now()) : $member->flagged_at])->save();
                }

                $top = $league->members->where('xp', '>', 0)->sortByDesc('xp')->take(League::PROMOTED);

                foreach ($top as $member) {
                    $member->forceFill(['promoted' => true])->save();
                    User::query()->whereKey($member->user_id)->where('league_tier', '<', League::TOP_TIER)->increment('league_tier');
                }

                $league->forceFill(['closed_at' => now()])->save();
            });
            $closed++;
        }

        $this->form($week);

        return $closed;
    }

    /**
     * This week's leagues for everyone who has joined and isn't in one yet: by tier, then voice
     * level, about 30 to a league.
     */
    private function form(Carbon $week): void
    {
        $placed = LeagueMember::query()->whereIn('league_id', League::query()->where('week', $week->toDateString())->select('id'))->pluck('user_id')->all();
        $waiting = User::query()->whereNotNull('league_joined_at')->whereNotNull('adult_confirmed_at')->whereNull('suspended_at')->whereNotIn('id', $placed)->get(['id', 'league_tier']);
        $learning = UserLearning::query()->whereIn('user_id', $waiting->pluck('id'))->get()->keyBy('user_id');

        foreach ($waiting->groupBy('league_tier') as $tier => $people) {
            $sorted = $people->sortBy(fn (User $u): int => self::level(self::xpIn($learning->get($u->id), $week)['total']))->values();

            foreach ($sorted->chunk(League::SIZE) as $group) {
                $league = new League;
                $league->forceFill(['week' => $week->toDateString(), 'tier' => (int) $tier])->save();

                foreach ($group as $person) {
                    (new LeagueMember)->forceFill(['league_id' => $league->id, 'user_id' => $person->id])->save();
                }
            }
        }
    }

    /**
     * Into a league of their tier this week with room, or a new one.
     */
    private function place(User $user, Carbon $week): void
    {
        $leagues = League::query()->where('week', $week->toDateString())->whereNull('closed_at');

        if (LeagueMember::query()->where('user_id', $user->id)->whereIn('league_id', (clone $leagues)->select('id'))->exists()) {
            return;
        }

        $league = (clone $leagues)->where('tier', $user->league_tier)->withCount('members')->lockForUpdate()->get()->first(fn (League $l): bool => $l->members_count < League::SIZE);

        if ($league === null) {
            $league = new League;
            $league->forceFill(['week' => $week->toDateString(), 'tier' => $user->league_tier])->save();
        }

        (new LeagueMember)->forceFill(['league_id' => $league->id, 'user_id' => $user->id])->save();
    }

    /**
     * People ranked by this week's capped XP, as the board shows them: never anyone the viewer is
     * blocked with, never a score or a recording.
     *
     * @param  list<User>  $people
     * @return list<array<string, mixed>>
     */
    private function ranked(array $people, User $viewer, Carbon $week): array
    {
        $blocked = $this->gallery->blockedIds($viewer);
        $people = array_values(array_filter($people, static fn (User $u): bool => ! in_array($u->id, $blocked, true)));
        $learning = UserLearning::query()->whereIn('user_id', array_map(static fn (User $u): int => $u->id, $people))->get()->keyBy('user_id');
        $rows = array_map(static function (User $u) use ($learning, $week, $viewer): array {
            $counted = self::xpIn($learning->get($u->id), $week);

            return [...GalleryViews::person($u), 'handle' => Gallery::hidden($u) ? null : $u->handle, 'level' => self::level($counted['total']), 'xp' => $counted['xp'], 'me' => $u->id === $viewer->id];
        }, $people);
        usort($rows, static fn (array $a, array $b): int => [$b['xp'], $a['name']] <=> [$a['xp'], $b['name']]);

        return array_values(array_map(static fn (array $row, int $i): array => [...$row, 'rank' => $i + 1], $rows, array_keys($rows)));
    }
}
