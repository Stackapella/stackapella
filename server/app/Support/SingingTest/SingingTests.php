<?php

declare(strict_types=1);

namespace App\Support\SingingTest;

use App\Exceptions\Refusal;
use App\Models\User;
use App\Support\Flags;

/**
 * The singing test's switches (docs/singing-test-v1.md): `singing_test` (staff first, per account, then everyone) and
 * `singing_test_boards` (off until enough singers have counted tests).
 */
final class SingingTests
{
    public static function on(?User $user): bool
    {
        return app(Flags::class)->onFor('singing_test', $user);
    }

    public static function boardsOn(?User $user): bool
    {
        return self::on($user) && app(Flags::class)->onFor('singing_test_boards', $user);
    }

    /** Not there while it's off for them. */
    public static function ensureOn(User $user): void
    {
        if (! self::on($user)) {
            throw new Refusal(404, 'not_found');
        }
    }
}
