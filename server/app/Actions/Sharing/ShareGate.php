<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Models\User;
use App\Support\Flags;
use App\Support\Trust\Limits;
use App\Support\Trust\SecurityLog;
use App\Support\Trust\TrustLevel;

/**
 * Whether a harmony may become unlisted now (docs/trust-and-safety-plan.md, "The limits"): never
 * while sharing is paused or for trust level 0, and counted toward the day's shares by level.
 */
final class ShareGate
{
    public function __construct(private readonly Flags $flags, private readonly TrustLevel $trust, private readonly Limits $limits, private readonly SecurityLog $securityLog) {}

    public function mayShare(User $user, string $ip): void
    {
        if ($this->flags->pausing('sharing') !== null) {
            throw new Refusal(423, 'paused');
        }

        if ($this->trust->of($user) === TrustLevel::NEW) {
            throw new Refusal(403, 'email_unverified');
        }

        self::count($this->limits->attempt('shares', $user, $ip));

        // Kept with the raw IP for 30 days, for investigating spam links (docs/trust-and-safety-plan.md).
        $this->securityLog->record(SecurityEventType::Share, $user, $ip);
    }

    public static function count(?int $retryAfter): void
    {
        if ($retryAfter !== null) {
            throw new Refusal(429, 'rate_limited', ['retryAfter' => $retryAfter], ['Retry-After' => (string) $retryAfter]);
        }
    }
}
