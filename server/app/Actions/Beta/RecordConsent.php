<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Enums\CreditMode;
use App\Exceptions\Refusal;
use App\Models\BetaSinger;
use App\Support\Accounts\Age;

/**
 * The singer accepts the beta agreement (this version), confirms they're 18 or over, and chooses
 * their credit. Accepting an older version than the current one is refused: the app shows the new
 * one first.
 */
final class RecordConsent
{
    public function handle(BetaSinger $singer, int $version, CreditMode $creditMode): BetaSinger
    {
        if ($version !== (int) config('beta.agreement_version')) {
            throw new Refusal(409, 'agreement_changed');
        }

        // The agreement is 18 and over: a birth date that says younger isn't overruled by it.
        if ($singer->user !== null) {
            Age::ensureMayClaimAdult($singer->user);
        }

        $singer->forceFill([
            'consent_version' => $version,
            'consented_at' => now(),
            'credit_mode' => $creditMode,
        ])->save();

        return $singer;
    }
}
