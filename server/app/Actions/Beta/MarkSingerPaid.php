<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\User;

/**
 * Every accepted, unpaid part of a singer marked as paid. The payment itself happens outside the
 * app (docs/decisions.md: Hillel pays singers their hourly rate).
 */
final class MarkSingerPaid
{
    public function handle(BetaSinger $singer, User $actor, ?string $ip = null): int
    {
        $count = BetaSubmission::query()
            ->whereIn('beta_assignment_id', $singer->assignments()->select('id'))
            ->where('status', SubmissionStatus::Accepted->value)
            ->where('payable_cents', '>', 0)
            ->whereNull('paid_at')
            ->update(['paid_at' => now()]);

        AuditLog::record('beta.paid', $singer, $actor, after: ['parts' => $count], ip: $ip);

        return $count;
    }
}
