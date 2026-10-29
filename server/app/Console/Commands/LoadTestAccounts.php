<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Users\DeleteAccount;
use App\Models\User;
use App\Support\Accounts\AppTokens;
use Illuminate\Console\Command;

/**
 * The load test's singers on the beta (docs/performance.md, H22): accounts made only for it, each
 * with an app sign-in whose access token tools/load/beta.js takes as TOKENS, and removed again
 * afterwards. They have no email (nothing is sent to them) and a handle that says what they are.
 * Never on the launch domain.
 */
final class LoadTestAccounts extends Command
{
    /** Their handles start with this, so --remove finds them all and nothing else. */
    public const HANDLE = 'loadtest_';

    protected $signature = 'app:load:accounts {--make=5 : How many to make} {--remove : Delete every load-test account instead}';

    protected $description = 'Make (or remove) the load test\'s singers and print their app tokens';

    public function handle(AppTokens $tokens, DeleteAccount $delete): int
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if (in_array($host, ['stackapella.com', 'www.stackapella.com', 'app.stackapella.com'], true)) {
            $this->error('Not on the launch domain: run it on the beta.');

            return self::FAILURE;
        }

        // LIKE's _ is a wildcard: escaped, so a handle like "loadtester" is never taken for one.
        $theirs = User::query()->where('handle', 'like', str_replace('_', '\\_', self::HANDLE).'%');

        if ($this->option('remove')) {
            $count = 0;

            // One at a time, as an account deletes itself (its relations are read as it goes).
            foreach ((clone $theirs)->pluck('id') as $id) {
                $user = User::query()->whereKey($id)->first();

                if ($user !== null) {
                    $delete->handle($user, null);
                    $count++;
                }
            }

            $this->info("Removed {$count} load-test accounts.");

            return self::SUCCESS;
        }

        $make = max(1, min(20, (int) $this->option('make')));
        $start = (int) (clone $theirs)->count();
        $access = [];

        for ($i = $start + 1; $i <= $start + $make; $i++) {
            $user = new User(['name' => 'Load test '.$i, 'email' => null, 'locale' => 'en']);
            $user->forceFill(['handle' => self::HANDLE.$i, 'adult_confirmed_at' => now(), 'age_confirmed_at' => now(), 'birth_year' => 1990, 'birth_month' => 1])->save();
            $access[] = $tokens->issue($user, ['name' => 'Load test', 'platform' => 'ios', 'appVersion' => 'load-test'], 'load_test', null)['accessToken'];
        }

        $this->info("Made {$make} load-test accounts. Their app tokens last an hour. On your Mac, from ~/Code/stackapella:");
        $this->line('k6 run -e BASE='.rtrim((string) config('app.url'), '/').' -e TOKENS='.implode(',', $access).' server/tool/load/beta.js');
        $this->line('Afterwards: php artisan app:load:accounts --remove');

        return self::SUCCESS;
    }
}
