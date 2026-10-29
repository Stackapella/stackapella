<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Admin\SecondFactorReset;
use App\Support\Admin\Admins;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * The way back in after losing every passkey, the phone and the recovery codes. Only runs from a
 * console (App Platform's, behind the DigitalOcean sign-in), is audited, and emails every admin.
 */
final class ResetSecondFactor extends Command
{
    protected $signature = 'app:admin:reset-second-factor {google_id : The Google ID, as listed in ADMIN_GOOGLE_IDS}';

    protected $description = 'Remove an admin\'s passkeys and authenticator app so they can set them up again';

    public function handle(Admins $admins): int
    {
        $user = User::query()->where('google_id', (string) $this->argument('google_id'))->first();

        if ($user === null || (! $user->is_admin && ! $user->is_editor)) {
            $this->error('No admin or editor with that Google ID.');

            return self::FAILURE;
        }

        if (! $this->confirm("Remove every passkey and the authenticator app for {$user->name}?", false)) {
            return self::FAILURE;
        }

        $user->passkeys()->delete();
        $user->secondFactor?->delete();
        AuditLog::record('admin.second_factor_reset', $user, null, reason: 'console');
        Notification::send($admins->all(), new SecondFactorReset($user));

        $this->info("Done. {$user->name} will be asked to set up a second factor at the next admin visit.");

        return self::SUCCESS;
    }
}
