<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppDevice;
use App\Models\AppPush;
use App\Models\DataExport;
use App\Models\EmailToken;
use App\Models\HandleHold;
use App\Models\SignInLink;
use App\Models\SupportMessage;
use App\Models\UserDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * What accounts leave behind: used or expired email links, expired handle holds, exports past
 * their 7 days (the file too, if the bucket's own expiry hasn't taken it), browsers not seen
 * for a year, app sign-ins a month after they were signed out or their refresh token ran out,
 * and support messages two years after their last word (docs/trust-and-safety-plan.md,
 * "Privacy, kept").
 */
final class PruneAccounts extends Command
{
    protected $signature = 'app:accounts:prune';

    protected $description = 'Delete used email links, expired handle holds, old exports, long-unseen browsers, old app sign-ins and old support messages';

    public function handle(): int
    {
        $tokens = EmailToken::query()->where(fn ($query) => $query->whereNotNull('used_at')->orWhere('expires_at', '<', now()))->where('created_at', '<', now()->subDay())->delete();
        $holds = HandleHold::query()->whereNotNull('held_until')->where('held_until', '<', now())->delete();
        $devices = UserDevice::query()->where('last_seen_at', '<', now()->subYear())->delete();
        // Sign-in links last 15 minutes: a day after, they're gone, used or not.
        SignInLink::query()->where('created_at', '<', now()->subDay())->delete();
        // Pushes are for the moment: a week after, they're gone (sent, dropped, or never sent).
        AppPush::query()->where('created_at', '<', now()->subDays(7))->delete();
        $apps = AppDevice::query()->where(fn ($query) => $query->where('revoked_at', '<', now()->subDays(30))->orWhere('refresh_expires_at', '<', now()->subDays(30)))->delete();
        $support = SupportMessage::query()->where('updated_at', '<', now()->subMonths(SupportMessage::KEEP_MONTHS))->delete();

        $exports = 0;
        DataExport::query()
            ->where(fn ($query) => $query->where('expires_at', '<', now())->orWhere(fn ($old) => $old->whereNull('expires_at')->where('created_at', '<', now()->subDays(30))))
            ->chunkById(200, function ($rows) use (&$exports): void {
                Storage::disk('private')->delete($rows->pluck('file_key')->filter()->values()->all());
                $exports += DataExport::query()->whereKey($rows->modelKeys())->delete();
            });

        $this->info("Deleted {$tokens} email links, {$holds} handle holds, {$exports} exports, {$devices} browsers, {$apps} app sign-ins and {$support} support messages.");

        return self::SUCCESS;
    }
}
