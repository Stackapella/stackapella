<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Flags;
use Illuminate\Support\Facades\Lang;

/**
 * The site's words for "teaming up is Plus" (docs/pricing-plan.md, top section; the plus_teams switch): learning alone
 * is free, whoever starts a shared song or a choir group needs Plus, and everyone they invite joins free. While the switch
 * is off the site says what it says today; on, each place that called friends or groups free reads its change from
 * lang/<locale>/teams.php instead, so Hillel flips the switch and the site changes with it. That file is keyed by the full
 * key it changes ("pages.pricing.rows"), kept apart so no list of pages ever meets a change as a page of its own, and
 * holds only what changes: an array is laid over the words (a list by its index, a page by its keys), a string replaces.
 *
 * Choir seats (the choir_seats switch, Hillel 2026-11-18) lay lang/<locale>/seats.php over that in the same way: a
 * choir's members no longer join free, each needs a seat.
 */
final class Teams
{
    public static function on(): bool
    {
        return app(Flags::class)->on('plus_teams');
    }

    /** Choir seats (the choir_seats switch, docs/choir-seats-plan.md): a choir's members need a seat each. */
    public static function seats(): bool
    {
        return app(Flags::class)->on('choir_seats');
    }

    /** The words at `$key`, with teaming up's change laid over them while its switch is on, then choir seats'. */
    public static function words(string $key): mixed
    {
        $words = Lang::get($key);

        foreach ([self::change($key), self::seatsChange($key)] as $change) {
            if ($change !== null) {
                $words = is_array($words) && is_array($change) ? array_replace_recursive($words, $change) : $change;
            }
        }

        return $words;
    }

    /**
     * A string's words, as __() gives them, or its change while the switch is on.
     *
     * @param  array<string, string|int|float>  $replace
     */
    public static function text(string $key, array $replace = []): string
    {
        $words = self::words($key);

        return is_string($words) ? strtr($words, array_combine(array_map(static fn (string $k): string => ':'.$k, array_keys($replace)), array_map('strval', $replace)) ?: []) : (string) __($key, $replace);
    }

    /** Choir seats' change (lang/<locale>/seats.php), laid over teaming up's, while choir_seats is on. */
    private static function seatsChange(string $key): mixed
    {
        if (! self::seats()) {
            return null;
        }
        $changes = Lang::get('seats');

        return is_array($changes) ? ($changes[$key] ?? null) : null;
    }

    private static function change(string $key): mixed
    {
        if (! self::on()) {
            return null;
        }
        $changes = Lang::get('teams');

        return is_array($changes) ? ($changes[$key] ?? null) : null;
    }
}
