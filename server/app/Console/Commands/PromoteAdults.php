<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Accounts\Age;
use Illuminate\Console\Command;

/**
 * Daily: someone whose birth month and year now make them 18 becomes an adult on their own, never
 * asked again (Age).
 */
final class PromoteAdults extends Command
{
    protected $signature = 'app:accounts:ages';

    protected $description = 'Mark the accounts that turned 18 (by their birth month and year) as adults';

    public function handle(): int
    {
        $promoted = 0;

        User::query()->whereNull('adult_confirmed_at')->whereNotNull('birth_month')->whereNotNull('birth_year')
            ->where('birth_year', '<=', now()->year - Age::ADULT)
            ->chunkById(500, function ($users) use (&$promoted): void {
                foreach ($users as $user) {
                    if (Age::isAtLeast((int) $user->birth_month, (int) $user->birth_year, Age::ADULT)) {
                        $user->forceFill(['adult_confirmed_at' => now()])->save();
                        $promoted++;
                    }
                }
            });

        $this->info("{$promoted} accounts turned 18.");

        return self::SUCCESS;
    }
}
