<?php

declare(strict_types=1);

namespace App\Actions\Voices;

use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Lend your voice's moderation (docs/lend-your-voice-plan.md, slice 3): a volunteer whose take is
 * refused, or whose published voice is removed, for abuse is blocked from claiming; refusals for
 * quality aren't counted. Paid beta singers are never blocked this way (Hillel manages them).
 */
final class VolunteerAbuse
{
    public static function block(?BetaSubmission $submission, User $admin, ?string $ip = null): void
    {
        $singer = $submission?->assignment?->singer;

        if (! $singer instanceof BetaSinger || ! $singer->volunteer || $singer->volunteer_blocked_at !== null) {
            return;
        }

        $singer->forceFill(['volunteer_blocked_at' => now()])->save();
        AuditLog::record('voices.volunteer_blocked', $singer, $admin, after: ['submission' => $submission->id], ip: $ip);
    }

    /** A removed voice's open reports are dealt with: upheld. */
    public static function settleReports(Model $voice): void
    {
        Report::query()->where('reportable_type', $voice->getMorphClass())->where('reportable_id', $voice->getKey())->where('status', Report::OPEN)
            ->update(['status' => Report::UPHELD, 'updated_at' => now()]);
    }
}
