<?php

declare(strict_types=1);

namespace App\Actions\SingingTest;

use App\Actions\Boards\Boards;
use App\Exceptions\Refusal;
use App\Models\SingingTest;
use App\Models\User;
use App\Support\SingingTest\SingingTests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * singingTests.boards.show: the singing test's Most improved and Best boards for a month (docs/singing-test-v1.md, "The
 * boards"), on the part boards' rules for who's on them (App\Actions\Boards\Boards). Read from the tests themselves: a
 * season is a UTC month of one test version; a singer appears after 3 counted tests on 2 or more days; Best ranks the
 * mean of the best 3 of the month's first 4 counted tests, Most improved that over their baseline (the better of their
 * first two counted tests ever), at most 30. Entries within THRESHOLD points share a place.
 */
final class SingingTestBoards
{
    public const THRESHOLD = 8.0;

    public const IMPROVED_CAP = 30.0;

    public const TESTS = 3;

    public const DAYS = 2;

    /** The tests of a month that make its season: later ones go to the history only. */
    private const SEASON_TESTS = 4;

    /** The best of them that count. */
    private const BEST_OF = 3;

    public function __construct(private readonly Boards $boards) {}

    /**
     * @return array<string, mixed>
     */
    public function show(User $user, string $board, string $scope, ?string $month): array
    {
        if (! SingingTests::boardsOn($user)) {
            throw new Refusal(404, 'not_found');
        }

        $start = $month !== null ? Carbon::createFromFormat('!Y-m', $month, 'UTC') : now()->utc()->startOfMonth();
        $start = ($start ?: now()->utc())->startOfMonth();
        $end = $start->copy()->addMonth();
        $versions = array_map('intval', (array) config('singing_test.test_versions'));
        // The newest test version's boards.
        $version = $versions === [] ? 1 : max($versions);

        $who = $scope === 'friends' ? [...$this->boards->friendIds($user), $user->id] : null;
        $tests = SingingTest::query()->where('test_version', $version)->where('status', SingingTest::COUNTED)->whereNull('hidden_at')
            ->where('taken_at', '>=', $start)->where('taken_at', '<', $end)
            ->when($who !== null, fn ($query) => $query->whereIn('user_id', $who))
            ->when($who === null, fn ($query) => $query->whereIn('user_id', User::query()->select('id')->whereNotNull('board_global_at')->whereNotNull('adult_confirmed_at')->whereNull('suspended_at')))
            ->orderBy('taken_at')->orderBy('id')
            ->get(['id', 'user_id', 'taken_at', 'total', 'flagged_at', 'reviewed_at'])
            ->groupBy('user_id');

        $mine = $tests->get($user->id) ?? SingingTest::query()->where('user_id', $user->id)->where('test_version', $version)->where('status', SingingTest::COUNTED)
            ->whereNull('hidden_at')->where('taken_at', '>=', $start)->where('taken_at', '<', $end)->orderBy('taken_at')->orderBy('id')
            ->get(['id', 'user_id', 'taken_at', 'total', 'flagged_at', 'reviewed_at']);

        $seasons = [];

        foreach ($tests as $userId => $theirs) {
            $season = $this->season($theirs);

            // A held test keeps its season off the global board for 48 hours, unless it's theirs.
            if ($season === null || ($scope === 'global' && $userId !== $user->id && $season['held'])) {
                continue;
            }

            $seasons[(int) $userId] = $season;
        }

        $baselines = $board === 'improved' ? $this->baselines(array_keys($seasons), $version) : [];
        $people = User::query()->whereKey(array_keys($seasons))->get()->keyBy('id');
        $entries = [];

        foreach ($seasons as $userId => $season) {
            $person = $people->get($userId);

            if (! $person instanceof User) {
                continue;
            }

            $score = $board === 'improved' ? min(self::IMPROVED_CAP, round($season['score'] - ($baselines[$userId] ?? $season['score']), 1)) : $season['score'];
            $entries[] = ['user' => $person, 'score' => $score, 'tests' => $season['tests'], 'at' => $season['at']];
        }

        usort($entries, static fn (array $a, array $b): int => [$b['score'], $a['at'], $a['user']->id] <=> [$a['score'], $b['at'], $b['user']->id]);
        $rank = static fn (float $score): int => 1 + count(array_filter($entries, static fn (array $e): bool => $e['score'] - $score > self::THRESHOLD));
        $shown = array_map(fn (array $e): array => [
            'id' => 'st_'.substr(hash('sha256', $e['user']->id.'|'.$start->format('Y-m').'|'.$version.'|'.config('app.key')), 0, 12),
            'rank' => $rank($e['score']),
            ...Boards::shown($e['user'], $scope),
            'score' => $e['score'],
            'tests' => $e['tests'],
            'you' => $e['user']->id === $user->id,
        ], array_slice($entries, 0, (int) config('boards.top', 10)));

        $days = $mine->map(static fn (SingingTest $t): string => $t->taken_at->utc()->toDateString())->unique()->count();
        $mineOn = array_values(array_filter($entries, static fn (array $e): bool => $e['user']->id === $user->id));

        return [
            'board' => $board,
            'scope' => $scope,
            'month' => $start->format('Y-m'),
            'endsAt' => $end->toIso8601ZuluString(),
            'testVersion' => $version,
            'threshold' => self::THRESHOLD,
            'entries' => $shown,
            'you' => [
                'score' => $mineOn !== [] ? $mineOn[0]['score'] : null,
                'rank' => $mineOn !== [] ? $rank($mineOn[0]['score']) : null,
                'tests' => min(self::SEASON_TESTS, $mine->count()),
                'days' => $days,
                'needs' => ['tests' => max(0, self::TESTS - $mine->count()), 'days' => max(0, self::DAYS - $days)],
            ],
            'onGlobal' => $user->board_global_at !== null,
        ];
    }

    /**
     * One singer's season from the month's counted tests, oldest first: null until it has enough.
     *
     * @param  Collection<int, SingingTest>  $tests
     * @return array{score: float, tests: int, at: string, held: bool}|null
     */
    private function season(Collection $tests): ?array
    {
        $first = $tests->take(self::SEASON_TESTS)->values();
        $days = $first->map(static fn (SingingTest $t): string => $t->taken_at->utc()->toDateString())->unique()->count();

        // When they joined the board: the third test that made the season.
        $joined = $first->get(self::TESTS - 1);

        if (! $joined instanceof SingingTest || $days < self::DAYS) {
            return null;
        }

        $best = $first->map(static fn (SingingTest $t): int => (int) $t->total)->sortDesc()->take(self::BEST_OF);
        $heldSince = now()->subHours(SingingTest::HOLD_HOURS);

        return [
            'score' => round($best->sum() / $best->count(), 1),
            'tests' => $first->count(),
            'at' => $joined->taken_at->utc()->toIso8601ZuluString(),
            'held' => $first->contains(static fn (SingingTest $t): bool => $t->flagged_at !== null && $t->reviewed_at === null && $t->flagged_at->gt($heldSince)),
        ];
    }

    /**
     * Each singer's baseline: the better of their first two counted tests ever, in this version.
     *
     * @param  list<int>  $userIds
     * @return array<int, float>
     */
    private function baselines(array $userIds, int $version): array
    {
        if ($userIds === []) {
            return [];
        }

        return SingingTest::query()->whereIn('user_id', $userIds)->where('test_version', $version)->where('status', SingingTest::COUNTED)->whereNull('hidden_at')
            ->orderBy('taken_at')->orderBy('id')->get(['user_id', 'total'])->groupBy('user_id')
            ->map(static fn (Collection $tests): float => (float) $tests->take(2)->max('total'))
            ->all();
    }
}
