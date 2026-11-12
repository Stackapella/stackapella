<?php

declare(strict_types=1);

namespace App\Support\Site;

use InvalidArgumentException;
use MessageFormatter;

/**
 * The app's own words (web/locales/<language>.json), for the site's drawings of the app: a button or a
 * label drawn on a page is read from the app, so the two never say different things. In the page's language, as the
 * app's t() reads it: a line with no translation, or whose English changed since it was translated (its
 * <language>.source.json), reads in English (frontend's Spanish walk, 2026-11-12: /es showed "Copy link").
 */
final class AppWords
{
    /** @var array<string, array<string, mixed>|null> each locale file read once, null when there is none */
    private static array $files = [];

    public static function get(string $key): string
    {
        $english = self::find(self::file('en') ?? [], $key);
        if ($english === null) {
            throw new InvalidArgumentException("The app has no words at [{$key}], or they aren't words.");
        }

        $locale = app()->getLocale();
        if ($locale === 'en' || ! preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $locale)) {
            return $english;
        }
        $translated = self::find(self::file($locale) ?? [], $key);
        $source = self::file($locale.'.source');

        // As the app: a translation counts only while it was made from today's English.
        return $translated !== null && ($source === null || self::find($source, $key) === $english) ? $translated : $english;
    }

    /** @return array<string, mixed>|null */
    private static function file(string $name): ?array
    {
        if (! array_key_exists($name, self::$files)) {
            $path = dirname(base_path()).'/web/locales/'.$name.'.json';
            self::$files[$name] = is_file($path) ? (array) json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : null;
        }

        return self::$files[$name];
    }

    /** @param  array<string, mixed>  $words */
    private static function find(array $words, string $key): ?string
    {
        $value = $words;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
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
        $words = MessageFormatter::formatMessage(app()->getLocale(), self::get($key), $values);
        if ($words === false) {
            throw new InvalidArgumentException("The app's [{$key}] doesn't format.");
        }

        return $words;
    }
}
