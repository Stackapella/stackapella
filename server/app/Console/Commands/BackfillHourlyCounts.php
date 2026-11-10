<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Stats\Counts;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The hours before hourly counting began, for the two counts our own records can date
 * (docs/growth-compare-plan.md, 5.2): sign_up from each account's created_at and plus_started from
 * each subscription's, the admins left out, a web subscription that never completed left out and
 * only the stores' production purchases, over the last 100 days. It fills only a kind's hours that
 * have no row yet, so it is safe to run twice. Visits, arrivals and lessons have no timestamps
 * anywhere, so they can't be backfilled; their older days are estimated from the daily totals.
 */
final class BackfillHourlyCounts extends Command
{
    protected $signature = 'app:counts:backfill-hours';

    protected $description = 'Fill the hourly sign_up and plus_started counts from the accounts and subscriptions (once; safe to repeat)';

    public function handle(): int
    {
        $since = now()->utc()->startOfHour()->subDays(PruneHourlyCounts::KEEP_DAYS);
        $admins = Counts::admins();
        $hour = "date_format(created_at, '%Y-%m-%d %H:00:00')";
        $byHour = static fn (Builder $query): array => $query->where('created_at', '>=', $since)->whereNotIn('user_id', $admins)
            ->groupByRaw($hour)->selectRaw($hour.' as h, count(*) as n')->pluck('n', 'h')->map(static fn (mixed $n): int => (int) $n)->all();

        $users = DB::table('users')->where('created_at', '>=', $since)->whereNotIn('id', $admins)
            ->groupByRaw($hour)->selectRaw($hour.' as h, count(*) as n')->pluck('n', 'h')->map(static fn (mixed $n): int => (int) $n)->all();
        $web = $byHour(DB::table('subscriptions')->where('type', 'default')->whereNotIn('stripe_status', ['incomplete', 'incomplete_expired']));
        $stores = $byHour(DB::table('store_subscriptions')->where('environment', 'Production'));
        $plus = $web;

        foreach ($stores as $h => $n) {
            $plus[$h] = ($plus[$h] ?? 0) + $n;
        }

        $added = $this->fill('sign_up', $users) + $this->fill('plus_started', $plus);
        $this->info("Filled {$added} hours of sign-ups and Plus.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts  UTC hour => count
     */
    private function fill(string $kind, array $counts): int
    {
        $have = array_flip(DB::table('usage_count_hours')->where('kind', $kind)->whereIn('hour', array_keys($counts))->pluck('hour')
            ->map(static fn (mixed $h): string => substr((string) $h, 0, 19))->all());
        $rows = [];

        foreach ($counts as $h => $n) {
            if (! isset($have[$h]) && $n > 0) {
                $rows[] = ['hour' => $h, 'kind' => $kind, 'audience' => 'account', 'source' => '', 'count' => $n];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('usage_count_hours')->insert($chunk);
        }

        return count($rows);
    }
}
