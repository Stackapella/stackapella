<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Check 1 of AdminGate: the database flag, set only from a console (App Platform's, behind the
 * DigitalOcean sign-in). The person must also be on ADMIN_GOOGLE_IDS (or EDITOR_GOOGLE_IDS) and
 * have signed in once with Google, which makes their account.
 */
final class GrantAdmin extends Command
{
    protected $signature = 'app:admin:grant {google_id : The Google ID, as listed in ADMIN_GOOGLE_IDS or EDITOR_GOOGLE_IDS} {--editor : Make an editor instead} {--revoke : Take the role away}';

    protected $description = 'Give (or take away) the admin or editor role';

    public function handle(): int
    {
        $user = User::query()->where('google_id', (string) $this->argument('google_id'))->first();

        if ($user === null) {
            $this->error('Nobody with that Google ID has signed in yet. Sign in once at /admin/sign-in, then run this again.');

            return self::FAILURE;
        }

        $column = $this->option('editor') ? 'is_editor' : 'is_admin';
        $value = ! $this->option('revoke');
        $before = (bool) $user->{$column};

        $user->forceFill([$column => $value])->save();
        AuditLog::record('admin.role.'.$column, $user, null, before: [$column => $before], after: [$column => $value], reason: 'console');

        $this->info(sprintf('%s is %s %s.', $user->name, $value ? 'now' : 'no longer', $this->option('editor') ? 'an editor' : 'an admin'));

        return self::SUCCESS;
    }
}
