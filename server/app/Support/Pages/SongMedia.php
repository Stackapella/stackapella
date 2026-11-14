<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Str;

/**
 * A song story's pictures (Hillel, 2026-11-12: "please try to add images and links to the songs pages to make them more
 * immersive"; the courses lead sources them, marketing lays them out). Each song's are in its stories folder,
 * media/<slug>/: the originals (<n>.jpg or .png, at most MAX_BYTES each and MAX_SONG_BYTES a song, long edge at most
 * MAX_EDGE), the WebP sizes app:stories:images makes beside them (<n>-480.webp, -960, -1440, never upscaled; their
 * sources' hashes in webp.json, so a changed original shows as stale), and media.json, a record each: alt and caption
 * in each language, date, source, library, call number, the item's page, and a licence from LICENCES only, with its
 * evidence (the coordinator, for Hillel: licences must be right). The site serves only the WebP files
 * (SongMediaController); never hotlinked.
 */
final class SongMedia
{
    /**
     * The only licences a picture may have, each with its readable name and its page. A public-domain picture points to
     * Creative Commons' Public Domain Mark, the page that says what "no known copyright" means, so its ImageObject has a
     * `license` and search can show it as licensable (the coordinator, 2026-11-13).
     */
    public const LICENCES = [
        'PD-old' => ['name' => 'Public domain', 'url' => 'https://creativecommons.org/publicdomain/mark/1.0/'],
        'PD-US-gov' => ['name' => 'Public domain (US government work)', 'url' => 'https://creativecommons.org/publicdomain/mark/1.0/'],
        'CC0' => ['name' => 'CC0', 'url' => 'https://creativecommons.org/publicdomain/zero/1.0/'],
        'LoC-no-known-restrictions' => ['name' => 'No known restrictions (Library of Congress)', 'url' => 'https://www.loc.gov/legal/understanding-copyright/'],
        'CC-BY-4.0' => ['name' => 'CC BY 4.0', 'url' => 'https://creativecommons.org/licenses/by/4.0/'],
    ];

    /** The WebP widths made of each picture; the middle one is the page's src. */
    public const WIDTHS = [480, 960, 1440];

    public const MAX_BYTES = 600 * 1024;

    public const MAX_SONG_BYTES = 2560 * 1024;

    public const MAX_EDGE = 1600;

    /**
     * A song's picture records, keyed by name (the file without its extension: "1", "2"), as media.json has them.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function records(string $slug): array
    {
        $path = self::folder($slug).'/media.json';
        $list = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $records = [];
        foreach (is_array($list) ? $list : [] as $record) {
            if (is_array($record) && is_string($record['file'] ?? null) && preg_match('/^[a-z0-9-]+\.(jpe?g|png)$/', $record['file']) === 1) {
                $records[pathinfo($record['file'], PATHINFO_FILENAME)] = $record;
            }
        }

        return $records;
    }

    /**
     * The pictures as a page shows them, in its language: each named one's sizes, alt, caption (as HTML, its
     * citations made by $cite), credit and licence. A picture with no WebP yet is left out.
     *
     * @param  list<string>  $names
     * @param  callable(string): string  $cite  a caption's Markdown to HTML, its [^id] numbered as the story's
     * @return array<string, array<string, mixed>>
     */
    public static function forPage(string $slug, array $names, string $language, callable $cite): array
    {
        $records = self::records($slug);
        $images = [];
        foreach ($names as $name) {
            $record = $records[$name] ?? null;
            $middle = self::folder($slug).'/'.$name.'-960.webp';
            if ($record === null || ! is_file($middle)) {
                continue;
            }
            [$width, $height] = getimagesize($middle) ?: [0, 0];
            $licence = (string) ($record['licence'] ?? '');
            $words = static fn (string $field): string => (string) ($record[$field][$language] ?? $record[$field]['en'] ?? '');
            $images[$name] = [
                'src' => self::url($slug, $name, 960),
                'srcset' => implode(', ', array_map(static fn (int $w): string => self::url($slug, $name, $w).' '.$w.'w', array_values(array_filter(self::WIDTHS, static fn (int $w): bool => is_file(self::folder($slug).'/'.$name.'-'.$w.'.webp'))))),
                'width' => (int) $width,
                'height' => (int) $height,
                'alt' => $words('alt'),
                'caption' => $cite($words('caption')),
                'date' => is_string($record['date'] ?? null) ? $record['date'] : null,
                'library' => is_string($record['library'] ?? null) ? $record['library'] : null,
                'callNumber' => is_string($record['callNumber'] ?? null) && $record['callNumber'] !== '' ? $record['callNumber'] : null,
                'credit' => (string) ($licence === 'CC-BY-4.0' ? ($record['creditText'] ?? '') : ($record['credit'] ?? '')),
                'licence' => $licence,
                'licenceName' => self::LICENCES[$licence]['name'] ?? $licence,
                'licenceUrl' => self::LICENCES[$licence]['url'] ?? null,
                'sourceUrl' => is_string($record['sourceUrl'] ?? null) ? $record['sourceUrl'] : null,
            ];
        }

        return $images;
    }

    /** A picture's WebP at a width, as the site serves it: its version (its bytes' hash) in the query, kept a year. */
    public static function url(string $slug, string $name, int $width): string
    {
        $file = self::folder($slug).'/'.$name.'-'.$width.'.webp';

        return '/song-media/'.$slug.'/'.$name.'-'.$width.'.webp'.(is_file($file) ? '?v='.substr((string) hash_file('xxh128', $file), 0, 12) : '');
    }

    /** The served file's path, or null when the address isn't one of a song's WebP sizes. */
    public static function served(string $slug, string $file): ?string
    {
        if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1 || preg_match('/^[a-z0-9-]+-('.implode('|', self::WIDTHS).')\.webp$/', $file) !== 1) {
            return null;
        }
        $path = self::folder($slug).'/'.$file;

        return is_file($path) ? $path : null;
    }

    /**
     * What's wrong with a song's pictures, one line each: a record's licence, evidence, credit or words, a file's size,
     * a WebP missing or made from an older original, and names placed but not recorded or recorded but not placed.
     *
     * @param  list<string>  $placed  the names the story places (and its hero)
     * @param  list<string>  $languages  the languages the story is written in
     * @return list<string>
     */
    public static function check(string $slug, array $placed, array $languages): array
    {
        $at = $slug.' (pictures): ';
        $records = self::records($slug);
        $problems = [];
        $made = is_file(self::folder($slug).'/webp.json') ? (array) json_decode((string) file_get_contents(self::folder($slug).'/webp.json'), true) : [];
        $total = 0;

        foreach (array_diff($placed, array_keys($records)) as $name) {
            $problems[] = $at.'![['.$name.']] has no record in media.json';
        }
        foreach (array_diff(array_keys($records), $placed) as $name) {
            $problems[] = $at.$name.' is in media.json but never placed';
        }
        foreach ($records as $name => $record) {
            $licence = $record['licence'] ?? null;
            if (! is_string($licence) || ! isset(self::LICENCES[$licence])) {
                $problems[] = $at.$name.': its licence must be one of '.implode(', ', array_keys(self::LICENCES));
            }
            if (trim((string) ($record['licenceEvidence'] ?? '')) === '') {
                $problems[] = $at.$name.': no licenceEvidence (the rights statement\'s URL and a quote, or the public-domain reasoning)';
            }
            if ($licence === 'CC-BY-4.0' ? trim((string) ($record['creditText'] ?? '')) === '' : trim((string) ($record['credit'] ?? '')) === '') {
                $problems[] = $at.$name.': no '.($licence === 'CC-BY-4.0' ? 'creditText' : 'credit');
            }
            foreach (['sourceUrl', 'date'] as $field) {
                if (trim((string) ($record[$field] ?? '')) === '') {
                    $problems[] = $at.$name.': no '.$field;
                }
            }
            foreach ($languages as $language) {
                foreach (['alt', 'caption'] as $field) {
                    if (trim((string) ($record[$field][$language] ?? '')) === '') {
                        $problems[] = $at.$name.': no '.$field.' in '.$language;
                    }
                }
            }
            $original = self::folder($slug).'/'.$record['file'];
            if (! is_file($original)) {
                $problems[] = $at.$name.': '.$record['file'].' is missing';

                continue;
            }
            $bytes = (int) filesize($original);
            $total += $bytes;
            [$w, $h] = getimagesize($original) ?: [0, 0];
            if ($bytes > self::MAX_BYTES || max($w, $h) > self::MAX_EDGE) {
                $problems[] = $at.$name.': over the caps ('.round($bytes / 1024).' KB, '.max($w, $h).' px; at most '.(self::MAX_BYTES / 1024).' KB and '.self::MAX_EDGE.' px)';
            }
            $stale = ($made[$record['file']] ?? null) !== hash_file('sha256', $original);
            foreach (self::WIDTHS as $width) {
                if ($width <= max($w, $h, self::WIDTHS[0]) && ($stale || ! is_file(self::folder($slug).'/'.$name.'-'.$width.'.webp'))) {
                    $problems[] = $at.$name.': its WebP is missing or stale: run php artisan app:stories:images '.$slug;
                    break;
                }
            }
        }
        if ($total > self::MAX_SONG_BYTES) {
            $problems[] = $at.'over '.(self::MAX_SONG_BYTES / 1024).' KB of originals in all';
        }

        return $problems;
    }

    /**
     * Makes a song's WebP sizes from its originals (never wider than the original), and records which original each
     * came from (webp.json). Run on a checkout, committed with the pictures; production only serves the files.
     *
     * @return list<string> the files written
     */
    public static function make(string $slug, int $quality = 80): array
    {
        $written = [];
        $made = [];
        foreach (self::records($slug) as $name => $record) {
            $original = self::folder($slug).'/'.$record['file'];
            if (! is_file($original)) {
                continue;
            }
            $image = Str::endsWith($record['file'], '.png') ? imagecreatefrompng($original) : imagecreatefromjpeg($original);
            if ($image === false) {
                continue;
            }
            $sourceWidth = imagesx($image);
            foreach (self::WIDTHS as $width) {
                $target = min($width, $sourceWidth);
                $resized = imagescale($image, $target, -1, IMG_BICUBIC);
                if ($resized === false) {
                    continue;
                }
                $out = self::folder($slug).'/'.$name.'-'.$width.'.webp';
                imagewebp($resized, $out, $quality);
                $written[] = $out;
            }
            $made[$record['file']] = hash_file('sha256', $original);
        }
        ksort($made);
        file_put_contents(self::folder($slug).'/webp.json', json_encode($made, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $written;
    }

    private static function folder(string $slug): string
    {
        return (string) config('content.stories').'/media/'.$slug;
    }
}
