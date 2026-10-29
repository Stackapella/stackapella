<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\User;

/**
 * End a singer's role (or their invite, before it's accepted). Their account, takes and guides stay.
 */
final class RevokeInvite
{
    public function handle(BetaSinger $singer, User $actor, ?string $ip = null): void
    {
        $singer->forceFill(['revoked_at' => now()])->save();
        AuditLog::record('beta.revoked', $singer, $actor, ip: $ip);
    }
}
