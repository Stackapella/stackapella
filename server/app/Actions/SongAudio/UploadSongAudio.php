<?php

declare(strict_types=1);

namespace App\Actions\SongAudio;

use App\Support\SongAudio\SongAudioFiles;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Puts the showreel agent's song renders on the media CDN, the way the showreel goes up (run where the Spaces keys are):
 * from its folder (manifest.json beside <slug>.mp3 and <slug>.m4a), each file checked against the manifest's bytes and
 * sha256, at an immutable key (SongAudioFiles::key). Then the checked songs are written into the committed manifest
 * (SongAudioFiles::MANIFEST), which is what the pages read once it's landed and deployed: upload first, then land.
 */
final class UploadSongAudio
{
    private const TYPES = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4'];

    /**
     * @return array{uploaded: int, unchanged: int, songs: list<string>, skipped: array<string, string>}
     */
    public function handle(string $folder, bool $check = false): array
    {
        $manifest = json_decode((string) @file_get_contents($folder.'/manifest.json'), true);
        if (! is_array($manifest) || ! is_array($manifest['songs'] ?? null)) {
            throw new \InvalidArgumentException("No manifest.json with songs in {$folder}.");
        }

        $result = ['uploaded' => 0, 'unchanged' => 0, 'songs' => [], 'skipped' => []];
        $good = [];
        foreach ($manifest['songs'] as $slug => $entry) {
            $slug = (string) $slug;
            $why = self::problem($folder, $slug, $entry);
            if ($why !== null) {
                $result['skipped'][$slug] = $why;

                continue;
            }
            /** @var array{version: int, files: list<array{name: string, bytes: int, sha256: string}>} $entry */
            $good[$slug] = $entry;
            $result['songs'][] = $slug;
            if ($check) {
                continue;
            }
            foreach ($entry['files'] as $file) {
                $key = SongAudioFiles::key($slug, $entry['version'], $file['name'], $file['sha256']);
                $disk = Storage::disk('media');
                if ($disk->exists($key) && $disk->size($key) === $file['bytes']) {
                    $result['unchanged']++;

                    continue;
                }
                $stream = fopen($folder.'/'.$file['name'], 'rb');
                $disk->writeStream($key, $stream === false ? throw new \RuntimeException("Couldn't open {$file['name']}.") : $stream, [
                    'visibility' => 'public',
                    'ContentType' => self::TYPES[pathinfo($file['name'], PATHINFO_EXTENSION)],
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);
                $result['uploaded']++;
            }
        }

        if (! $check && $good !== []) {
            // The committed manifest keeps what it had for songs not in this folder, and takes each checked song's new entry.
            $committed = SongAudioFiles::manifest();
            ksort($good);
            $songs = [...$committed, ...$good];
            ksort($songs);
            File::put(SongAudioFiles::path(), json_encode(['source' => $manifest['source'] ?? null, 'songs' => $songs], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }

        return $result;
    }

    private static function problem(string $folder, string $slug, mixed $entry): ?string
    {
        if (preg_match('/^[a-z0-9-]{1,80}$/', $slug) !== 1 || ! is_array($entry)) {
            return 'not a song slug';
        }
        if (! is_int($entry['version'] ?? null) || ! is_numeric($entry['duration'] ?? null) || ! is_int($entry['channels'] ?? null) || ! is_array($entry['guides'] ?? null) || ! is_array($entry['files'] ?? null) || $entry['files'] === []) {
            return 'its manifest entry is missing version, duration, channels, guides or files';
        }
        foreach ($entry['files'] as $file) {
            $name = is_array($file) ? (string) ($file['name'] ?? '') : '';
            $path = $folder.'/'.$name;
            if (basename($name) !== $name || pathinfo($name, PATHINFO_FILENAME) !== $slug || ! isset(self::TYPES[pathinfo($name, PATHINFO_EXTENSION)])) {
                return "{$name}: not <slug>.mp3 or <slug>.m4a";
            }
            if (! is_file($path) || filesize($path) !== ($file['bytes'] ?? null) || ! is_string($file['sha256'] ?? null) || hash_file('sha256', $path) !== $file['sha256']) {
                return "{$name}: missing, or not the bytes and sha256 the manifest says";
            }
        }

        return null;
    }
}
