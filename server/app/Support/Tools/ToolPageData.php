<?php

declare(strict_types=1);

namespace App\Support\Tools;

use App\Support\Site\SitePages;
use Illuminate\Support\Facades\Lang;

/**
 * What a tool page hands its tool besides its words, so it needs no request of its own and works offline with the
 * page: the vocal key finder's songs from the live library (each with its key, its melody's range and a link to
 * learn it), and the mic test's next steps. The page passes these as mount options (js/public/tools/page.js).
 */
final class ToolPageData
{
    public function __construct(private readonly KnownSongs $songs) {}

    /** @return array<string, mixed> */
    public function for(string $tool): array
    {
        return match ($tool) {
            'vocal-key-finder' => ['songs' => array_map(static fn (array $song): array => [
                'name' => $song['title'],
                'key' => $song['key'],
                'low' => $song['low'],
                'high' => $song['high'],
                'href' => '/songs/'.$song['slug'],
            ], $this->songs->all())],
            // The mic test's next steps, only to tools that are live.
            'mic-test' => ['next' => array_values(array_map(
                static fn (array $next): array => ['label' => (string) $next[0], 'href' => '/tools/'.$next[1]],
                array_filter((array) Lang::get('site.tools.pages.mic-test.next'), static fn (mixed $next): bool => is_array($next) && in_array($next[1] ?? null, SitePages::TOOLS, true)),
            ))],
            default => [],
        };
    }
}
