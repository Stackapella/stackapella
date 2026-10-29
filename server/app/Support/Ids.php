<?php

declare(strict_types=1);

namespace App\Support;

use Sqids\Sqids;
use Throwable;

/**
 * Public IDs for server records: an integer key shown as a Sqid, never as a number
 * (docs/architecture.md, "Identity and IDs"). Records made on the device keep their UUID instead.
 */
final class Ids
{
    private static ?Sqids $sqids = null;

    public static function encode(int $id): string
    {
        return self::sqids()->encode([$id]);
    }

    /**
     * The key behind a Sqid, or null for anything that isn't exactly one we would have made. A
     * Sqid that decodes but isn't the canonical spelling is refused, so one record never has two
     * addresses.
     */
    public static function decode(string $sqid): ?int
    {
        if ($sqid === '' || strlen($sqid) > 32) {
            return null;
        }

        try {
            $numbers = self::sqids()->decode($sqid);
        } catch (Throwable) {
            // A character outside the alphabet: no record has that ID.
            return null;
        }

        if (count($numbers) !== 1 || $numbers[0] < 1 || self::encode($numbers[0]) !== $sqid) {
            return null;
        }

        return $numbers[0];
    }

    private static function sqids(): Sqids
    {
        return self::$sqids ??= new Sqids((string) config('app.sqids_alphabet'), (int) config('app.sqids_min_length'));
    }
}
