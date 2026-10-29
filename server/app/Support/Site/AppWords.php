<?php

declare(strict_types=1);

namespace App\Support\Site;

use InvalidArgumentException;
use MessageFormatter;

/**
 * The app's own words (web/locales/en.json), for the site's drawings of the app: a button or a
 * label drawn on a page is read from the app, so the two never say different things.
 */
final class AppWords
{
    /** @var array<string, mixed>|null */
    private static ?array $words = null;

    public static function get(string $key): string
    {
        self::$words ??= (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/locales/en.json'), true, flags: JSON_THROW_ON_ERROR);

        $value = self::$words;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                throw new InvalidArgumentException("The app has no words at [{$key}].");
            }
            $value = $value[$segment];
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException("The app's [{$key}] isn't words.");
        }

        return $value;
    }

    /**
     * The app's words with their {placeholders} filled (simple ones only: a plural isn't).
     *
     * @param  array<string, string|int>  $values
     */
    public static function fill(string $key, array $values): string
    {
        return strtr(self::get($key), array_combine(array_map(static fn (string $k): string => '{'.$k.'}', array_keys($values)), array_map('strval', array_values($values))));
    }

    /**
     * Words with a count, written as the app writes them ({count, plural, ...}): formatted as the
     * app's i18n does, through ICU.
     *
     * @param  array<string, int|string>  $values
     */
    public static function format(string $key, array $values): string
    {
        $words = MessageFormatter::formatMessage('en', self::get($key), $values);
        if ($words === false) {
            throw new InvalidArgumentException("The app's [{$key}] doesn't format.");
        }

        return $words;
    }
}
