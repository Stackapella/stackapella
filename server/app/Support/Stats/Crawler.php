<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Illuminate\Http\Request;

/**
 * A well-known crawler (config counts.crawlers): search engines, SEO and AI bots and link previews run
 * our pages' scripts too, so their counts would read as visits. Read from the user agent and dropped,
 * every kind alike; the agent itself is never kept.
 */
final class Crawler
{
    public static function is(Request $request): bool
    {
        $agent = strtolower((string) $request->userAgent());

        if ($agent === '') {
            return false;
        }

        foreach ((array) config('counts.crawlers') as $name) {
            if (is_string($name) && $name !== '' && str_contains($agent, strtolower($name))) {
                return true;
            }
        }

        return false;
    }
}
