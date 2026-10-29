<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Legal\LegalText;
use App\Support\Pages\Landing;
use App\Support\Pages\SongPage;
use App\Support\Site\OpenSource;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * /guides/<slug> (short guides people search for, each ending in a song to try) and
 * /legal/<page> (the legal pages), from their Markdown in resources/ (as headings, paragraphs and
 * lists; never HTML). No session, cached 5 minutes.
 */
final class DocumentPagesController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'public, max-age=300'];

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [...self::HEADERS, ...(config('ops.indexing') === true ? [] : ['X-Robots-Tag' => 'noindex'])];
    }

    /** The guides, in the order a learner meets them. */
    public const GUIDES = [
        // In the order a beginner meets them: what it is, the parts, your voice, then practising.
        'what-is-harmony', 'satb-explained', 'find-your-voice-type', 'how-to-sing-harmony', 'find-your-part', 'hold-your-part',
        'practise-a-choir-part', 'a-round-for-beginners', 'ear-training', 'singing-in-tune', 'breathing-basics', 'arranging-a-cappella',
    ];

    /**
     * The guides this install has (an install without the content has none).
     *
     * @return list<string>
     */
    public static function guides(): array
    {
        $text = app(LegalText::class);

        return array_values(array_filter(self::GUIDES, static fn (string $slug): bool => $text->exists($slug, 'guides')));
    }

    /** The legal pages that are public (the beta agreement is shown in the app). */
    public const LEGAL = ['terms', 'privacy', 'refunds', 'community-guidelines', 'copyright', 'coach-agreement', 'marketplace-terms', 'accessibility', 'security'];

    /** Legal pages there only once Stackapella is open source (the open_source flag). */
    public const OPEN_SOURCE_LEGAL = ['self-hosting-terms'];

    public function guide(string $slug, LegalText $text): Response
    {
        $guide = in_array($slug, self::GUIDES, true) ? $this->read($text, $slug, 'guides') : null;

        if ($guide === null) {
            return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, self::headers());
        }

        $song = $guide['song'] !== null ? Template::query()->listed()->whereNotNull('current_version_id')->with('currentVersion')->where('slug', $guide['song'])->first() : null;
        $url = Hosts::publicUrl().'/guides/'.$slug;
        $songPage = $song !== null ? SongPage::of($song) : null;
        // The next guides, in the list's order after this one.
        $guides = self::guides();
        $at = (int) array_search($slug, $guides, true);
        $next = array_slice([...array_slice($guides, $at + 1), ...array_slice($guides, 0, $at)], 0, 3);

        return response()->view('public.guide', [
            'guide' => [
                'slug' => $slug,
                'title' => $guide['title'],
                'description' => (string) $guide['description'],
                // A heading "1. Hear your part alone" is a step on the guide's path: its number apart.
                'blocks' => array_map(static fn (array $block): array => $block['type'] === 'heading' && preg_match('/^(\d+)\.\s+(.+)$/', (string) $block['text'], $m) === 1
                    ? [...$block, 'step' => (int) $m[1], 'text' => $m[2]]
                    : [...$block, 'step' => null], $guide['blocks']),
                'reviewed' => $guide['reviewed'],
                'song' => $songPage,
                'next' => array_map(fn (string $other): array => ['slug' => $other, 'title' => (string) ($this->read($text, $other, 'guides')['title'] ?? $other)], $next),
            ],
            // The guide's own song plays beside it when it's free (a Plus song never shows its notes).
            'line' => $song !== null && $songPage !== null && ! $songPage['plus'] ? Landing::line($song) : null,
            'meta' => ['title' => $guide['title'], 'description' => (string) $guide['description'], 'url' => $url],
        ], 200, self::headers());
    }

    public function legal(string $page, LegalText $text): Response
    {
        $listed = in_array($page, self::LEGAL, true) || (in_array($page, self::OPEN_SOURCE_LEGAL, true) && OpenSource::on());
        $doc = $listed ? $this->read($text, $page, 'legal') : null;

        if ($doc === null) {
            return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, self::headers());
        }

        return response()->view('public.legal', [
            'document' => ['page' => $page, 'title' => $doc['title'], 'version' => $doc['version'], 'reviewed' => $doc['reviewed'], 'blocks' => $doc['blocks']],
            'meta' => ['title' => $doc['title'], 'description' => '', 'url' => Hosts::publicUrl().'/legal/'.$page],
        ], 200, self::headers());
    }

    /**
     * @return array{version: int, reviewed: bool, language: string, title: string, description: string|null, song: string|null, blocks: list<array{type: string, level: int|null, text: string|null, items: list<string>|null}>}|null
     */
    private function read(LegalText $text, string $name, string $folder): ?array
    {
        try {
            return $text->read($name, 'en', $folder);
        } catch (RuntimeException) {
            // Listed but not written yet (a legal page still with the lawyer): not there.
            return null;
        }
    }
}
