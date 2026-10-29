<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Prices;

/**
 * A price someone set (a coach's offering) as the site shows it: our three currencies with their
 * symbol, any other with its code.
 */
final class Money
{
    public static function format(int $cents, string $currency): string
    {
        $currency = strtolower($currency);

        return in_array($currency, Prices::CURRENCIES, true)
            ? Prices::format($cents, $currency)
            : strtoupper($currency).' '.number_format($cents / 100, 2);
    }
}
