<?php

declare(strict_types=1);

namespace App\Support\SongAudio;

use App\Models\Template;
use App\Models\TemplateGuide;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A song's whole-song audio (Hillel: "expose the main audio for the song", in stereo): the showreel agent's renders of
 * every part mixed, on the media CDN, listed in a committed manifest (UploadSongAudio writes it once they're up). A page
 * plays a song's render only while it's of the song's current version.
 */
final class SongAudioFiles
{
    public const MANIFEST = 'data/song_audio.json';

    public static function path(): string
    {
        return (string) config('content.song_audio', resource_path(self::MANIFEST));
    }

    /** @return array<string, array<string, mixed>> each song's entry, by slug (a small file, read when asked) */
    public static function manifest(): array
    {
        $path = self::path();
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) && is_array($data['songs'] ?? null) ? $data['songs'] : [];
    }

    /** A render's immutable key on the media disk: song-audio/<slug>/<version>-<sha8>.<ext>. */
    public static function key(string $slug, int $version, string $name, string $sha256): string
    {
        return 'song-audio/'.$slug.'/'.$version.'-'.substr($sha256, 0, 8).'.'.pathinfo($name, PATHINFO_EXTENSION);
    }

    /**
     * The render of the song's current version, for its page's player and MusicRecording: null without one.
     *
     * @return array{mp3: string|null, m4a: string|null, seconds: float, credit: string|null}|null
     */
    public static function forPage(Template $template): ?array
    {
        $entry = self::manifest()[(string) $template->slug] ?? null;
        if (! is_array($entry) || ($entry['version'] ?? null) !== $template->currentVersion?->version) {
            return null;
        }
        $urls = ['mp3' => null, 'm4a' => null];
        foreach ((array) ($entry['files'] ?? []) as $file) {
            $format = pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION);
            if (array_key_exists($format, $urls)) {
                $urls[$format] = Storage::disk('media')->url(self::key((string) $template->slug, (int) $entry['version'], (string) $file['name'], (string) $file['sha256']));
            }
        }

        return $urls['mp3'] === null && $urls['m4a'] === null ? null
            : [...$urls, 'seconds' => (float) ($entry['duration'] ?? 0), 'credit' => is_string($entry['credit'] ?? null) && $entry['credit'] !== '' ? $entry['credit'] : null];
    }

    /**
     * The songs whose render is missing or out of date: none, another version's, or another voice now a part's default
     * guide than the one mixed in. By slug, with why, for the showreel agent's render.py.
     *
     * @param  Collection<int, Template>  $songs  with currentVersion loaded
     * @return array<string, string>
     */
    public static function stale(Collection $songs): array
    {
        $defaults = TemplateGuide::query()->whereIn('template_version_id', $songs->pluck('current_version_id'))->where('is_default', true)->get()->groupBy('template_version_id');
        $manifest = self::manifest();

        $stale = [];
        foreach ($songs as $template) {
            $entry = $manifest[(string) $template->slug] ?? null;
            $parts = array_column((array) (((array) ($template->currentVersion->data ?? []))['parts'] ?? []), 'key');
            $now = array_fill_keys(array_map('strval', $parts), null);
            foreach ($defaults->get($template->current_version_id, collect()) as $guide) {
                $now[$guide->part_key] = $guide->sqid();
            }
            $why = match (true) {
                ! is_array($entry) => 'no render',
                ($entry['version'] ?? null) !== $template->currentVersion?->version => 'rendered from version '.((int) ($entry['version'] ?? 0)).', now '.$template->currentVersion?->version,
                (array) ($entry['guides'] ?? []) != $now => 'its guide voices changed',
                default => null,
            };
            if ($why !== null) {
                $stale[(string) $template->slug] = $why;
            }
        }
        ksort($stale);

        return $stale;
    }
}
