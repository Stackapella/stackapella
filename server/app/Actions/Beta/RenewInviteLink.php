<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A new link replaces a lost or forwarded one before it's accepted: the old one stops working at
 * once. Also lifts a revocation, and can set a new expiry.
 */
final class RenewInviteLink
{
    public function handle(BetaSinger $singer, ?CarbonInterface $expiresAt, User $actor, ?string $ip = null): string
    {
        // Once accepted, the singer signs in like anyone else: there's no link left to replace.
        if ($singer->user_id !== null) {
            throw new Refusal(409, 'already_accepted');
        }

        $token = InviteSinger::newToken();
        $singer->forceFill(['token_hash' => hash('sha256', $token), 'revoked_at' => null, 'expires_at' => $expiresAt ?? now()->addDays(InviteSinger::DAYS)])->save();
        AuditLog::record('beta.link_renewed', $singer, $actor, ip: $ip);

        return $token;
    }
}
