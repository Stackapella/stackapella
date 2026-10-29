<?php

declare(strict_types=1);

namespace App\Support\Trust;

use App\Models\Take;
use App\Models\User;

/**
 * Every account's trust level, from our own records (docs/trust-and-safety-plan.md, "Trust
 * levels"). Limits and permissions read it; it's never shown to the person.
 *
 * - 0 New: an email and password, not yet verified (or a disposable address without Google or Apple);
 * - 1 Verified: the email verified, or signed in with Google or Apple;
 * - 2 Singer: verified, at least 3 days old, and real singing (from M4: 30 minutes of practice or
 *   2 recorded parts);
 * - 3 Trusted: a Plus subscription charged once without a dispute (from M5), or set by Hillel.
 *
 * An upheld report drops an account to 1 for 30 days, and a chargeback to 0 (trust_lowered_until
 * and the override); Hillel can set any level, with a note.
 */
final class TrustLevel
{
    public const NEW = 0;

    public const VERIFIED = 1;

    public const SINGER = 2;

    public const TRUSTED = 3;

    public function of(User $user): int
    {
        if ($user->trust_level_override !== null) {
            return $user->trust_level_override;
        }

        $level = $this->earned($user);

        if ($user->trust_lowered_until !== null && $user->trust_lowered_until->isFuture()) {
            $level = min($level, self::VERIFIED);
        }

        return $level;
    }

    private function earned(User $user): int
    {
        // Google, Apple and Event Schedule vouch for the account, with or without an address.
        $byProvider = $user->google_id !== null || $user->apple_id !== null || $user->event_schedule_id !== null;

        if (! $byProvider && ($user->email_verified_at === null || ($user->email !== null && WordLists::isDisposableEmail($user->email)))) {
            return self::NEW;
        }

        if ($this->hasPaidPlus($user)) {
            return self::TRUSTED;
        }

        if ($user->created_at !== null && $user->created_at->lte(now()->subDays(3)) && $this->hasSung($user)) {
            return self::SINGER;
        }

        return self::VERIFIED;
    }

    /**
     * Real singing, from synced practice and takes: those reach the server in M4.
     */
    /**
     * Real singing: 2 recorded parts in the cloud (practice minutes join this when they sync).
     * Lists load ready_takes_count (withCount) so this asks nothing more.
     */
    private function hasSung(User $user): bool
    {
        $count = $user->getAttribute('ready_takes_count') ?? Take::query()->where('user_id', $user->id)->where('status', Take::READY)->count();

        return (int) $count >= 2;
    }

    /**
     * A Plus subscription charged once without a dispute: billing arrives in M5.
     */
    /**
     * A Plus payment went through (a dispute sets the override to 0, which wins).
     */
    private function hasPaidPlus(User $user): bool
    {
        return $user->plus_paid_at !== null;
    }
}
