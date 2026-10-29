<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The real-instrument samples on the media CDN (docs/decisions.md, "voices first, instruments
 * welcome"): each built instrument is a folder, uploaded once to instruments/<name>/<version>/ and
 * never changed (a fix is a new version), cached for a year. instruments/index.json lists each
 * instrument's current version and its manifest, and is cached for five minutes, so a new
 * version reaches the apps within minutes while the files themselves never change. The manifest's
 * format is contract/instruments.md, shared by the web's and the apps' players; the build is
 * server/tool/instruments/build.py. CORS: the media bucket answers any origin (decisions.md, "Public
 * media is fetched with CORS from any origin").
 */
final class Instruments
{
    public const INDEX = 'instruments/index.json';

    private const LISTED = 'instruments-index-listed';

    public const NAME = '/^[a-z][a-z0-9-]{1,39}$/';

    public const VERSION = '/^[0-9]{1,4}(\.[0-9]{1,4}){0,2}$/';

    /** What a built instrument folder may hold, and the type each is served as. */
    public const TYPES = [
        'json' => 'application/json',
        'm4a' => 'audio/mp4',
        'mp4' => 'audio/mp4',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'flac' => 'audio/flac',
        'wav' => 'audio/wav',
        'webm' => 'audio/webm',
        'txt' => 'text/plain; charset=utf-8',
    ];

    /** A built instrument is at most this big (a piano's samples are about 30 MB in AAC). */
    public const MAX_BYTES = 300 * 1024 * 1024;

    private const IMMUTABLE = 'public, max-age=31536000, immutable';

    private const SHORT = 'public, max-age=300';

    /**
     * The files of a built folder, checked: a manifest.json, only the allowed types, no hidden
     * files or links, under the size cap. Paths are relative to the folder.
     *
     * @return list<string>
     */
    public static function files(string $folder, ?string $name = null, ?string $version = null): array
    {
        $root = realpath($folder);

        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException("{$folder} isn't a folder.");
        }

        if (! is_file($root.'/manifest.json') || json_decode((string) file_get_contents($root.'/manifest.json'), true) === null) {
            throw new RuntimeException('The folder needs a manifest.json that is valid JSON.');
        }

        $files = [];
        $bytes = 0;
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($walk as $file) {
            /** @var \SplFileInfo $file */
            $relative = substr($file->getPathname(), strlen($root) + 1);

            if ($file->isLink() || str_starts_with(basename($relative), '.') || str_contains($relative, '/.')) {
                throw new RuntimeException("{$relative}: no hidden files or links.");
            }

            if (! $file->isFile()) {
                continue;
            }

            if (! isset(self::TYPES[strtolower($file->getExtension())]) || preg_match('#^[A-Za-z0-9._/-]+$#', $relative) !== 1) {
                throw new RuntimeException("{$relative}: not a sample, manifest or text file, or an odd name (letters, digits, dots, dashes and underscores only).");
            }

            $bytes += (int) $file->getSize();
            $files[] = $relative;
        }

        if ($bytes > self::MAX_BYTES) {
            throw new RuntimeException('The folder is over '.intdiv(self::MAX_BYTES, 1024 * 1024).' MB.');
        }

        self::matchesManifest($root, $files);
        self::checkFormat((array) json_decode((string) file_get_contents($root.'/manifest.json'), true), $name, $version);
        sort($files);

        return $files;
    }

    /**
     * Every file the manifest lists (`files: [{ path, bytes, sha256 }]`, the frontend's format) is
     * in the folder, at that size and hash: a cut-short or corrupt build never reaches the CDN.
     *
     * @param  list<string>  $files
     */
    private static function matchesManifest(string $root, array $files): void
    {
        $manifest = (array) json_decode((string) file_get_contents($root.'/manifest.json'), true);

        if (! is_array($manifest['files'] ?? null) || $manifest['files'] === []) {
            throw new RuntimeException('The manifest needs its files: [{ "path", "bytes", "sha256" }].');
        }

        foreach ($manifest['files'] as $i => $entry) {
            $path = is_array($entry) && is_string($entry['path'] ?? null) ? $entry['path'] : null;

            if ($path === null || ! in_array($path, $files, true)) {
                throw new RuntimeException('The manifest lists '.($path ?? "file {$i}").", which isn't in the folder.");
            }

            if (($entry['bytes'] ?? null) !== filesize($root.'/'.$path) || ! is_string($entry['sha256'] ?? null) || ! hash_equals(strtolower($entry['sha256']), (string) hash_file('sha256', $root.'/'.$path))) {
                throw new RuntimeException("{$path} doesn't match the manifest's bytes and sha256.");
            }
        }
    }

    /**
     * The manifest is in contract/instruments.md's format 1, for the name and version uploaded:
     * the fields a player relies on are there and in range, and the zones cover every key (or
     * every velocity of each piece) without gaps, and without two zones for the same key (or the
     * same round robin of a piece) and velocity.
     *
     * @param  array<mixed>  $manifest
     */
    private static function checkFormat(array $manifest, ?string $name, ?string $version): void
    {
        $fail = static fn (string $why): never => throw new RuntimeException("The manifest isn't contract/instruments.md's format 1: {$why}.");

        if (($manifest['format'] ?? null) !== 1) {
            $fail('format must be 1');
        }

        if (($name !== null && ($manifest['name'] ?? null) !== $name) || ($version !== null && ($manifest['version'] ?? null) !== $version)) {
            $fail("its name and version aren't {$name} {$version}");
        }

        $kind = $manifest['kind'] ?? null;
        $int = static fn (mixed $v, int $lo, int $hi): bool => is_int($v) && $v >= $lo && $v <= $hi;

        if (! in_array($kind, ['pitched', 'drums'], true) || ! $int($manifest['sampleRate'] ?? null, 8000, 192000) || ! $int($manifest['channels'] ?? null, 1, 2)) {
            $fail('kind, sampleRate or channels is missing or out of range');
        }

        if (! is_string($manifest['title'] ?? null) || ! is_numeric($manifest['release'] ?? null) || ! is_string($manifest['licence'] ?? null) || ! is_string($manifest['credit'] ?? null)) {
            $fail('title, release, licence or credit is missing');
        }

        if (isset($manifest['gain']) && (! is_numeric($manifest['gain']) || $manifest['gain'] <= 0 || $manifest['gain'] > 16)) {
            $fail('gain must be a number above 0, up to 16');
        }

        $zones = $manifest['zones'] ?? null;
        $listed = array_column((array) $manifest['files'], 'path');

        if (! is_array($zones) || $zones === [] || ! array_is_list($zones)) {
            $fail('it has no zones');
        }

        $covered = [];
        $taken = [];

        foreach ($zones as $i => $zone) {
            $ok = is_array($zone) && in_array($zone['file'] ?? null, $listed, true) && str_ends_with((string) $zone['file'], '.mp3')
                && $int($zone['frames'] ?? null, 1, 60 * 192000) && $int($zone['encoderDelay'] ?? null, 0, 4096)
                && is_numeric($zone['onset'] ?? null) && $zone['onset'] >= 0 && $zone['onset'] < 1
                && $int($zone['velLo'] ?? null, 1, 127) && $int($zone['velHi'] ?? null, 1, 127) && $zone['velLo'] <= $zone['velHi'];
            $ok = $ok && ($kind === 'pitched'
                ? $int($zone['root'] ?? null, 0, 127) && $int($zone['lo'] ?? null, 0, 127) && $int($zone['hi'] ?? null, 0, 127) && $zone['lo'] <= $zone['hi'] && is_numeric($zone['tune'] ?? null)
                : is_string($zone['piece'] ?? null) && preg_match('/^[a-z][a-z-]{1,30}$/', $zone['piece']) === 1 && $int($zone['rr'] ?? null, 1, 16) && array_key_exists('key', $zone) && ($zone['key'] === null || $int($zone['key'], 0, 127)));

            if (! $ok) {
                $fail("zone {$i} is missing a field, or has one out of range");
            }

            // What each (key or piece) and velocity is covered by: one zone each (a piece, one
            // for each of its round robins).
            $what = $kind === 'pitched' ? range($zone['lo'], $zone['hi']) : [$zone['piece']];

            foreach ($what as $key) {
                foreach (range($zone['velLo'], $zone['velHi']) as $velocity) {
                    $slot = $kind === 'pitched' ? $key : $key.' '.$zone['rr'];

                    if (isset($taken[$slot][$velocity])) {
                        $fail(($kind === 'pitched' ? "key {$key}" : "piece {$key}, round robin {$zone['rr']},")." has two zones at velocity {$velocity}");
                    }

                    $taken[$slot][$velocity] = true;
                    $covered[$key][$velocity] = true;
                }
            }
        }

        foreach ($kind === 'pitched' ? range(0, 127) : array_keys($covered) as $key) {
            if (count($covered[$key] ?? []) !== 127) {
                $fail(($kind === 'pitched' ? "key {$key}" : "piece {$key}")." isn't covered at every velocity");
            }
        }
    }

    /**
     * Uploads the folder as instruments/<name>/<version>/, then makes it the current version in the
     * index. A version that's already there is refused: what's uploaded never changes.
     *
     * @return array{files: int, manifest: string}
     */
    public static function upload(string $folder, string $name, string $version, bool $makeCurrent = true): array
    {
        if (preg_match(self::NAME, $name) !== 1 || preg_match(self::VERSION, $version) !== 1) {
            throw new RuntimeException('The name is lowercase letters, digits and dashes (2 to 40); the version is like 1, 1.2 or 1.2.3.');
        }

        $files = self::files($folder, $name, $version);
        $disk = Storage::disk('media');
        $prefix = "instruments/{$name}/{$version}";

        if ($disk->exists($prefix.'/manifest.json') || $disk->files($prefix, true) !== []) {
            throw new RuntimeException("{$prefix} is already uploaded. A change is a new version.");
        }

        $root = (string) realpath($folder);

        // The manifest last, so a reader never finds it before the samples it names.
        foreach ([...array_values(array_diff($files, ['manifest.json'])), 'manifest.json'] as $file) {
            $stream = fopen($root.'/'.$file, 'rb');
            $disk->writeStream($prefix.'/'.$file, $stream === false ? throw new RuntimeException("Couldn't open {$file}.") : $stream, [
                'visibility' => 'public',
                'ContentType' => self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))],
                'CacheControl' => self::IMMUTABLE,
            ]);
        }

        $manifest = $disk->url($prefix.'/manifest.json');

        if ($makeCurrent) {
            self::setCurrent($name, $version);
        }

        return ['files' => count($files), 'manifest' => $manifest];
    }

    /**
     * Makes an uploaded version the current one (also to go back to an earlier one).
     */
    public static function setCurrent(string $name, string $version): void
    {
        if (preg_match(self::NAME, $name) !== 1 || preg_match(self::VERSION, $version) !== 1) {
            throw new RuntimeException("{$name} {$version} isn't an instrument's name and version.");
        }

        $disk = Storage::disk('media');

        if (! $disk->exists("instruments/{$name}/{$version}/manifest.json")) {
            throw new RuntimeException("instruments/{$name}/{$version} hasn't been uploaded.");
        }

        $index = self::index();
        $index['instruments'][$name] = ['version' => $version, 'manifest' => $disk->url("instruments/{$name}/{$version}/manifest.json"), 'updatedAt' => now()->utc()->toIso8601ZuluString()];
        ksort($index['instruments']);

        $disk->put(self::INDEX, (string) json_encode(['instruments' => array_values(array_map(static fn (string $key, array $entry): array => ['name' => $key, ...$entry], array_keys($index['instruments']), $index['instruments']))], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", [
            'visibility' => 'public',
            'ContentType' => 'application/json',
            'CacheControl' => self::SHORT,
        ]);
        Cache::forget(self::LISTED);
    }

    /**
     * The index as it is, keyed by instrument name.
     *
     * @return array{instruments: array<string, array{version: string, manifest: string, updatedAt: string}>}
     */
    public static function index(): array
    {
        $disk = Storage::disk('media');
        $read = $disk->exists(self::INDEX) ? json_decode((string) $disk->get(self::INDEX), true) : null;
        $instruments = [];

        foreach (is_array($read['instruments'] ?? null) ? $read['instruments'] : [] as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null) && is_string($entry['version'] ?? null) && is_string($entry['manifest'] ?? null)) {
                $instruments[$entry['name']] = ['version' => $entry['version'], 'manifest' => $entry['manifest'], 'updatedAt' => (string) ($entry['updatedAt'] ?? '')];
            }
        }

        return ['instruments' => $instruments];
    }

    public static function indexUrl(): string
    {
        return Storage::disk('media')->url(self::INDEX);
    }

    /**
     * The index's URL for the apps, once an instrument has been uploaded: until then null, and
     * they play the synth. Whether it's there is looked up at most every five minutes, and at once
     * after an upload.
     */
    public static function listedIndexUrl(): ?string
    {
        return Cache::remember(self::LISTED, now()->addMinutes(5), static fn (): bool => Storage::disk('media')->exists(self::INDEX)) ? self::indexUrl() : null;
    }
}
