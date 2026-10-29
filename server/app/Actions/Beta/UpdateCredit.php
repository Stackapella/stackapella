<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Enums\CreditMode;
use App\Models\BetaSinger;

/**
 * A singer chooses to be credited by their account's name, or to stay anonymous, and whether that
 * credit links to their public profile, at any time; the catalogue shows it at once (and a new name
 * too, from their profile).
 */
final class UpdateCredit
{
    public function handle(BetaSinger $singer, ?CreditMode $creditMode, ?bool $linksProfile = null): BetaSinger
    {
        $singer->forceFill(array_filter([
            'credit_mode' => $creditMode,
            'credit_links_profile' => $linksProfile,
        ], static fn (mixed $value): bool => $value !== null))->save();

        return $singer;
    }
}
