<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\FlagUser;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * A feature switched on (or off) for one staff account while it stays off for everyone else (Flags::onFor): for staff
 * to try something on production first, own_choir's Modal end-to-end test the first (2026-11-22). Feature switches
 * only, staff accounts only, each change in the audit log.
 */
final class FlagAccount extends Command
{
    protected $signature = 'app:flags:account {flag : a feature switch, e.g. own_choir} {email : a staff account} {--off : take it off for them}';

    protected $description = 'Turn a feature switch on (or off) for one staff account only';

    public function handle(): int
    {
        $flag = (string) $this->argument('flag');
        $definition = config('flags.'.$flag);

        if (! is_array($definition) || ($definition['kind'] ?? null) !== 'feature') {
            $this->error("{$flag} isn't a feature switch.");

            return self::FAILURE;
        }

        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null || ! ($user->is_admin || $user->is_editor)) {
            $this->error('No staff account (admin or editor) with that email.');

            return self::FAILURE;
        }

        if ($this->option('off')) {
            FlagUser::query()->where('flag', $flag)->where('user_id', $user->id)->get()->each->delete();
        } else {
            FlagUser::query()->firstOrCreate(['flag' => $flag, 'user_id' => $user->id]);
        }

        $on = ! $this->option('off');
        AuditLog::record('flag.account', $user, null, after: ['flag' => $flag, 'on' => $on], reason: 'Console: a feature for one staff account');
        $this->info($flag.' is '.($on ? 'on' : 'off')." for {$user->email} (account {$user->sqid()}).");

        return self::SUCCESS;
    }
}
