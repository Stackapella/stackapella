<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Flags;
use InvalidArgumentException;

/**
 * Whether a feature the public pages talk about is open yet (config/site.php): by its flag, or by
 * `live` for one without. A feature that isn't shows "Coming soon"; its page is indexed all the same.
 */
final class ComingSoon
{
    public static function open(string $feature): bool
    {
        $definition = config('site.features.'.$feature);

        if (! is_array($definition)) {
            throw new InvalidArgumentException("No feature [{$feature}] in config/site.php.");
        }

        return isset($definition['flag']) ? app(Flags::class)->on((string) $definition['flag']) : ($definition['live'] ?? false) === true;
    }

    public static function soon(string $feature): bool
    {
        return ! self::open($feature);
    }
}
