<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * What's new (/whats-new): the entries in resources/whats-new/en.json, newest first, written by
 * hand when something people can use ships. Nothing behind a flag goes in until it's open.
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

        $list = array_values(array_map(static fn (mixed $e): array => [
            'date' => (string) ($e['date'] ?? ''),
            'day' => Carbon::parse((string) ($e['date'] ?? ''))->isoFormat('D MMMM YYYY'),
            'title' => (string) ($e['title'] ?? ''),
            'body' => (string) ($e['body'] ?? ''),
        ], $entries));
        usort($list, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $list;
    }
}
