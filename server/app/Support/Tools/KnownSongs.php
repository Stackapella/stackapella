<?php

declare(strict_types=1);

namespace App\Support\Tools;

use App\Models\Template;
use App\Support\Pages\SongPage;
use App\Support\Templates\Catalogue;
use Illuminate\Support\Facades\Cache;

/**
 * The vocal key finder's "pick a known song" (docs/tools-plan.md): each song in the live library
 * (Catalogue::songs: our own, listed, with a current version; never withdrawn, hidden or a person's)
 * that is reviewed, with its key and its melody's lowest and highest notes. Only what the song pages
 * already show: never the notes themselves. The tool's page passes it in as page data; cached ten
 * minutes, and forgotten when the songs are synced.
 */
final class KnownSongs
{
    public const CACHE = 'tools.known-songs';

    public function __construct(private readonly Catalogue $catalogue) {}

    /**
     * @return list<array{slug: string, title: string, key: string, low: int, high: int, lowName: string, highName: string}>
     */
    public function all(): array
    {
        /** @var list<array{slug: string, title: string, key: string, low: int, high: int, lowName: string, highName: string}> */
        return Cache::remember(self::CACHE, 600, fn (): array => $this->catalogue->songs()
            ->map(static function (Template $template): ?array {
                $data = (array) ($template->currentVersion->data ?? []);
                $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
                $melody = array_values(array_filter($parts, static fn (array $part): bool => ($part['melody'] ?? false) === true))[0] ?? null;
                $low = is_array($melody) ? ($melody['range']['low'] ?? null) : null;
                $high = is_array($melody) ? ($melody['range']['high'] ?? null) : null;

                if ($template->currentVersion?->reviewed !== true || ! is_int($low) || ! is_int($high) || ! is_string($data['key'] ?? null) || $data['key'] === '') {
                    return null;
                }

                return [
                    'slug' => (string) $template->slug,
                    'title' => (string) $template->title,
                    'key' => $data['key'],
                    'low' => $low,
                    'high' => $high,
                    'lowName' => SongPage::note($low),
                    'highName' => SongPage::note($high),
                ];
            })
            ->filter()
            ->values()
            ->all());
    }
}
