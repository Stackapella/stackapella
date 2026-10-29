<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Puts the landing's film on the media CDN (app:showreel:upload): the renders in a folder (they're
 * never in git), checked against the manifest that is (server/public/videos/showreel/manifest.json),
 * then uploaded to showreel/<version>/ with their types, cached for a year. A new render is a new
 * manifest, so a new folder: a page never mixes two renders, and nothing cached goes stale.
 */
final class ShowreelUpload
{
    public const PREFIX = 'showreel';

    /** What a render may hold, and the type each is served as. */
    public const TYPES = [
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'm4a' => 'audio/mp4',
        'opus' => 'audio/ogg',
        'webp' => 'image/webp',
        'jpg' => 'image/jpeg',
        'vtt' => 'text/vtt; charset=utf-8',
    ];

    private const IMMUTABLE = 'public, max-age=31536000, immutable';

    /**
     * The render's files in `$folder`, checked: the folder's manifest is the one in git (so the
     * pages ask for this render), and every file it lists is there, at its size and sha256, of a
     * type the CDN serves, with every file the pages use among them.
     *
     * @return list<string> the file names, in upload order
     */
    public static function files(string $folder): array
    {
        $root = realpath($folder);

        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException("{$folder} isn't a folder.");
        }

        $committed = Showreel::manifest();

        if (! is_file($committed)) {
            throw new RuntimeException('There is no manifest.json in server/public/videos/showreel: render the film first.');
        }

        if (! is_file($root.'/manifest.json') || ! hash_equals((string) hash_file('sha256', $committed), (string) hash_file('sha256', $root.'/manifest.json'))) {
            throw new RuntimeException("The folder's manifest.json isn't the one in git: commit the render's manifest first, so the pages ask for this render.");
        }

        $manifest = json_decode((string) file_get_contents($committed), true);

        if (! is_array($manifest) || ! is_array($manifest['files'] ?? null) || $manifest['files'] === []) {
            throw new RuntimeException('The manifest needs its files: [{ "name", "bytes", "sha256" }].');
        }

        $names = [];

        foreach ($manifest['files'] as $i => $entry) {
            $name = is_array($entry) && is_string($entry['name'] ?? null) ? $entry['name'] : null;

            if ($name === null || preg_match('/^[a-z0-9][a-z0-9.-]*$/', $name) !== 1 || ! isset(self::TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))])) {
                throw new RuntimeException('The manifest lists '.($name ?? "file {$i}").': not a video, sound, picture or captions file, or an odd name.');
            }

            $path = $root.'/'.$name;

            if (! is_file($path) || is_link($path)) {
                throw new RuntimeException("{$name} isn't in the folder.");
            }

            if (($entry['bytes'] ?? null) !== filesize($path) || ! is_string($entry['sha256'] ?? null) || ! hash_equals(strtolower($entry['sha256']), (string) hash_file('sha256', $path))) {
                throw new RuntimeException("{$name} doesn't match the manifest's bytes and sha256.");
            }

            $names[] = $name;
        }

        $wanted = array_map(static fn (string $url): string => basename((string) parse_url($url, PHP_URL_PATH)), self::pageFiles());

        if (($missing = array_values(array_diff($wanted, $names))) !== []) {
            throw new RuntimeException('The manifest is missing what the pages use: '.implode(', ', $missing).'.');
        }

        sort($names);

        return $names;
    }

    /**
     * Uploads the render, skipping files already there at their size (so a cut-off upload can
     * run again).
     *
     * @return array{uploaded: int, skipped: int, url: string}
     */
    public static function upload(string $folder): array
    {
        $names = self::files($folder);
        $root = (string) realpath($folder);
        $disk = Storage::disk('media');
        $prefix = self::PREFIX.'/'.Showreel::version();
        $uploaded = 0;

        foreach ($names as $name) {
            if ($disk->exists($prefix.'/'.$name) && $disk->size($prefix.'/'.$name) === filesize($root.'/'.$name)) {
                continue;
            }

            $stream = fopen($root.'/'.$name, 'rb');
            $disk->writeStream($prefix.'/'.$name, $stream === false ? throw new RuntimeException("Couldn't open {$name}.") : $stream, [
                'visibility' => 'public',
                'ContentType' => self::TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))],
                'CacheControl' => self::IMMUTABLE,
            ]);
            $uploaded++;
        }

        return ['uploaded' => $uploaded, 'skipped' => count($names) - $uploaded, 'url' => self::baseUrl()];
    }

    /** What SHOWREEL_URL is set to: the CDN's showreel folder (each render is a folder in it). */
    public static function baseUrl(): string
    {
        return rtrim(Storage::disk('media')->url(self::PREFIX), '/');
    }

    /**
     * Every file URL the landing uses.
     *
     * @return list<string>
     */
    private static function pageFiles(): array
    {
        $files = Showreel::files();

        return [...array_values(array_diff_key($files['dark'], ['posterSet' => 1])), ...array_values(array_diff_key($files['light'], ['posterSet' => 1])), $files['captions'],
            ...array_map(static fn (string $entry): string => explode(' ', $entry)[0], [...explode(', ', $files['dark']['posterSet']), ...explode(', ', $files['light']['posterSet'])])];
    }
}
