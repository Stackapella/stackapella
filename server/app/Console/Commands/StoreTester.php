<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\StoreTesters;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Marks an account as a store tester (App Review's demo account, a TestFlight tester): only theirs
 * get Plus from sandbox purchases. `--off` takes it back.
 */
final class StoreTester extends Command
{
    protected $signature = 'app:store:tester {email} {--off}';

    protected $description = 'Let an account\'s sandbox App Store and Google Play purchases give Plus (or stop it)';

    public function handle(StoreTesters $testers): int
    {
        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No account has that email.');

            return self::FAILURE;
        }

        $testers->set($user, ! $this->option('off'), null, 'console');
        $this->info($this->option('off') ? "{$user->email} is no longer a store tester." : "{$user->email} is a store tester: sandbox purchases give Plus.");

        return self::SUCCESS;
    }
}
