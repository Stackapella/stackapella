<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Account\GrowthViewerInvite;
use App\Models\AuditLog;
use App\Models\GrowthViewer;
use App\Support\Admin\GrowthViewers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * A growth viewer (App\Support\Admin\GrowthViewers), given or taken away only from a console (App Platform's, behind the
 * DigitalOcean sign-in), and logged. Granting sends one invite to the address: who asked, what they can see and how to
 * sign in. The first Google account to sign in with the address is kept as theirs.
 */
final class GrantViewer extends Command
{
    protected $signature = 'app:viewer:grant {email : The address they sign in to Google with} {--revoke : Take the access away} {--note= : One more line for the invite, as written}';

    protected $description = 'Give (or take away) view-only access to Admin\'s anonymous growth numbers';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('That isn\'t an email address.');

            return self::FAILURE;
        }

        $viewer = GrowthViewer::query()->where('email', $email)->first();

        if ($this->option('revoke')) {
            if ($viewer === null || $viewer->revoked_at !== null) {
                $this->info("{$email} has no access to take away.");

                return self::SUCCESS;
            }

            $viewer->forceFill(['revoked_at' => now()])->save();
            AuditLog::record('admin.viewer.revoke', $viewer, null, before: ['active' => true], after: ['active' => false], reason: 'console');
            $this->info("{$email} can no longer see the growth numbers.");

            return self::SUCCESS;
        }

        if (GrowthViewers::active($email) !== null) {
            $this->info("{$email} can already see the growth numbers. No email sent.");

            return self::SUCCESS;
        }

        // Granted again after a revoke: a fresh start, so whichever Google account signs in first with it is theirs.
        $viewer ??= new GrowthViewer(['email' => $email]);
        $viewer->forceFill(['granted_at' => now(), 'revoked_at' => null, 'google_id' => null])->save();
        AuditLog::record('admin.viewer.grant', $viewer, null, before: ['active' => false], after: ['active' => true], reason: 'console');

        $note = trim((string) $this->option('note'));
        Mail::to($email)->send(new GrowthViewerInvite($email, $note !== '' ? $note : null));
        $this->info("{$email} can now see the growth numbers. The invite is on its way.");

        return self::SUCCESS;
    }
}
