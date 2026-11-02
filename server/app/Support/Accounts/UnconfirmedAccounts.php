<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Password sign-ups whose address was never confirmed (Hillel: "Prune after 14 days, free on
 * Google/Apple", 2026-11-01). They can't sign in, so they hold nothing: they go after 14 days, and
 * sooner when the address's owner proves it another way (Google or Apple, or confirming an email
 * change), so a stranger's sign-up never keeps someone's address from them.
 */
final class UnconfirmedAccounts
{
    public const DAYS = 14;

    /**
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()->whereNull('email_verified_at')->whereNotNull('email')
            ->whereNull('google_id')->whereNull('apple_id')->whereNull('event_schedule_id');
    }

    /** The address's unconfirmed holder, if any, goes: its owner has just proven it. */
    public static function release(?string $email): void
    {
        if ($email === null || $email === '') {
            return;
        }

        self::query()->where('email', strtolower($email))->get()->each(fn (User $user) => $user->delete());
    }

    /** Those older than 14 days go. */
    public static function prune(): int
    {
        $gone = 0;
        self::query()->where('created_at', '<', now()->subDays(self::DAYS))->chunkById(200, function ($users) use (&$gone): void {
            foreach ($users as $user) {
                $user->delete();
                $gone++;
            }
        });

        return $gone;
    }
}
