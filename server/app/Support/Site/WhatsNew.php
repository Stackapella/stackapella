<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Languages\Dates;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * What's new (/whats-new): the entries in resources/whats-new/en.json, newest first, written by
 * hand when something people can use ships. Nothing behind a flag goes in until it's open. In Spanish, each line from
 * resources/whats-new/es.json (keyed by the English).
 */
final class WhatsNew
{
    /**
     * @return list<array{date: string, day: string, title: string, body: string}>
     */
    public static function entries(): array
    {
        $file = (string) config('content.whats_new');

        // An install without the content has no entries (config/content.php).
        if (! is_file($file)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($entries)) {
            throw new InvalidArgumentException('resources/whats-new/en.json must be a list.');
        }

        // In another language, each title and body from the file beside it (resources/whats-new/<locale>.json, keyed by the
        // English, as the site's other words are), else the English.
        $locale = app()->getLocale();
        $words = $locale === 'en' ? [] : self::words(dirname($file).'/'.$locale.'.json');
        $say = static fn (string $english): string => $words[$english] ?? $english;
        $list = array_values(array_map(static fn (mixed $e): array => [
            'date' => (string) ($e['date'] ?? ''),
            'day' => Dates::dayMonthYear(Carbon::parse((string) ($e['date'] ?? ''))),
            'title' => $say((string) ($e['title'] ?? '')),
            'body' => $say((string) ($e['body'] ?? '')),
        ], $entries));
        usort($list, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $list;
    }

    /**
     * A language's lines, keyed by the English, or none when it has no file yet.
     *
     * @return array<string, string>
     */
    private static function words(string $path): array
    {
        $map = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($map) ? array_filter($map, static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH) : [];
    }
}
