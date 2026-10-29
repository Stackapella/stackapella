<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Take;
use App\Models\User;

/**
 * When a part stops playing a take, that take is kept: it stays the part's, listed by
 * parts.takes.list, counting toward storage, until it's deleted (takes.delete, to the bin for 30
 * days). Hillel: "Maybe keep all takes so users can swap to try" (PartTakes). Nothing is binned
 * here any more; what's left is the bookkeeping of a replacement still on its way.
 */
final class BinTakes
{
    /**
     * A part's take was replaced: the old one is kept, so there's nothing to do but forget any
     * replacement it was itself still waiting on.
     */
    public function replaced(User $user, int $previousId, ?Take $now): void
    {
        if ($now !== null && $now->replaces_take_id !== null) {
            $now->forceFill(['replaces_take_id' => null])->save();
        }
    }

    /**
     * The new take is ready: what it replaced stays kept.
     */
    public function replacementReady(Take $take): void
    {
        if ($take->replaces_take_id !== null) {
            $take->forceFill(['replaces_take_id' => null])->save();
        }
    }
}
