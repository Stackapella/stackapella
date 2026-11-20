<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\User;

/**
 * admin.beta.singers.aiLicence.offer: the singer sees the AI voice licence in the app from now on. Until the lawyer's OK
 * the only way it's offered (docs/ai-voices-build-plan.md, phase 1b). Offering again changes nothing.
 */
final class OfferAiLicence
{
    public function handle(BetaSinger $singer, User $admin, ?string $ip = null): BetaSinger
    {
        if ($singer->ai_licence_offered_at === null) {
            $singer->forceFill(['ai_licence_offered_at' => now()])->save();
            AuditLog::record('ai.licence_offered', $singer, $admin, ip: $ip);
        }

        return $singer;
    }
}
