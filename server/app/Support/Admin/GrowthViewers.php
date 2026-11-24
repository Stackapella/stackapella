<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\GrowthViewer;
use App\Models\User;

/**
 * Growth viewers (Hillel, 2026-11-23): advisors who read Admin's anonymous growth numbers and nothing else. ROUTES is
 * the whole of what a viewer may open, the server's own allow-list (EnsureAdmin): every other admin route answers them
 * 403, so never a person's account, email, group, request, refund, setting or any action.
 */
final class GrowthViewers
{
    /** The admin routes a viewer may open: who they are, the way in (the second factor), and anonymous totals. */
    public const ROUTES = [
        'admin.me',
        'admin.growth.show',
        'admin.growth.choirs',
        // The same pages' numbers as CSV (Hillel, 2026-11-23), audit-logged.
        'admin.growth.export',
        // The AI choice card (docs/ai-optional-plan.md): counts by choice and age band, small ones suppressed.
        'admin.growth.ai',
        // The Growth page's chart of devices: counts only. Not admin.counts.show, whose countries aren't folded.
        'admin.reach.show',
        'admin.funnel.show',
        'admin.dropoffs.show',
        'admin.sources.show',
        'admin.second-factor.setup',
        'admin.second-factor.passkey.options',
        'admin.second-factor.passkey.store',
        'admin.second-factor.totp',
        'admin.second-factor.finish',
        'admin.second-factor.challenge',
        'admin.second-factor.challenge.options',
        'admin.second-factor.challenge.passkey',
        'admin.second-factor.challenge.code',
    ];

    public static function mayOpen(?string $route): bool
    {
        return $route !== null && in_array($route, self::ROUTES, true);
    }

    /** The viewer granted this address and not revoked, if any. */
    public static function active(string $email): ?GrowthViewer
    {
        $email = strtolower(trim($email));

        return $email === '' ? null : GrowthViewer::query()->where('email', $email)->whereNull('revoked_at')->first();
    }

    /**
     * A fresh Google sign-in whose address Google confirmed: the first Google account to sign in with a granted address
     * is kept as its viewer's, and only that account is one from then on.
     */
    public static function pin(User $user, string $verifiedEmail): void
    {
        $viewer = self::active($verifiedEmail);

        if ($viewer !== null && $viewer->google_id === null && (string) $user->google_id !== '') {
            $viewer->forceFill(['google_id' => (string) $user->google_id])->save();
        }
    }
}
