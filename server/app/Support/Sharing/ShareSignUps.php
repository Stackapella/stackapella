<?php

declare(strict_types=1);

namespace App\Support\Sharing;

use App\Models\Harmony;
use App\Models\User;

/**
 * Sign-ups from a share, recorded with the harmony (docs/go-to-market-plan.md, M6): the shared
 * page's links carry ?ref=h-<id>, which the sign-up keeps as `ref`. Our own records, no
 * analytics.
 */
final class ShareSignUps
{
    public static function record(User $user, ?string $ref): void
    {
        if ($ref === null || ! str_starts_with($ref, 'h-')) {
            return;
        }

        $harmony = Harmony::query()->where('public_id', substr($ref, 2))->where('user_id', '!=', $user->id)->first(['id']);

        if ($harmony === null) {
            return;
        }

        $user->forceFill(['signup_harmony_id' => $harmony->id])->save();
        Harmony::query()->whereKey($harmony->id)->increment('sign_ups');
    }
}
