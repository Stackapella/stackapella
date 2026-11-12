<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Legal\LegalText;
use App\Support\Pages\Landing;
use App\Support\Pages\SongPage;
use App\Support\Pages\SongParts;
use App\Support\Site\OpenSource;
use App\Support\Templates\Catalogue;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
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
        'practise-a-choir-part', 'a-round-for-beginners', 'ear-training', 'singing-in-tune', 'practise-intonation-with-a-drone', 'breathing-basics', 'arranging-a-cappella',
        // An a cappella group's drum kit (docs/search-content-plan.md, item 1): the search the dropped course was found for.
        'vocal-percussion',
        // The choir set (docs/search-content-plan.md, item 5), each checked for demand first: the voicings, a descant.
        'choir-voicings', 'what-is-a-descant', 'find-your-starting-note',
        // Written and checked on 2026-11-08 but never listed until now: sight-singing (retitled for the solfege
        // searches, docs/search-content-plan.md) and vibrato.
        'sight-reading-for-singers', 'how-to-sing-with-vibrato',
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
        // In the page's language when the guide is written in it (resources/guides/<lang>/), else English.
        $guide = in_array($slug, self::GUIDES, true) ? $this->read($text, $slug, 'guides', app()->getLocale()) : null;

        if ($guide === null) {
            return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, self::headers());
        }

        $song = $guide['song'] !== null ? Template::query()->listed()->whereNotNull('current_version_id')->with('currentVersion')->where('slug', $guide['song'])->first() : null;
        // A guide names its song only while the site may show it (Catalogue::open()).
        $song = $song !== null && Catalogue::isOpen($song) ? $song : null;
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
                // Its song's part pages while they're open (SongParts::hasPages), so the guide can point at each part.
                'parts' => $song !== null && SongParts::hasPages($song) ? array_map(static fn (array $part): array => [
                    'name' => $part['name'], 'url' => '/songs/'.$song->slug.'/'.$part['key'],
                ], SongParts::parts($song)) : [],
                // The guide's questions (pages.guides.faqs.<slug>), shown after its words, with FAQPage data.
                'faq' => array_values(array_filter((array) Lang::get('pages.guides.faqs.'.$slug, []), 'is_array')),
                // The section a panel of the four parts' ranges follows (pages.guides.ranges.after.<slug>), if any.
                'ranges' => Lang::has('pages.guides.ranges.after.'.$slug) ? (string) Lang::get('pages.guides.ranges.after.'.$slug) : null,
                'next' => array_map(fn (string $other): array => ['slug' => $other, 'title' => (string) ($this->read($text, $other, 'guides', app()->getLocale())['title'] ?? $other)], $next),
            ],
            // The guide's own song plays beside it whenever it's open ($song is null otherwise): Hillel's "Yes, play for
            // guests" (2026-11-08) for every open song on a public page, as the part pages do (the coordinator,
            // 2026-11-10). Before, only a bundled song played here (Hillel, 2026-10-27).
            'line' => $song !== null ? Landing::line($song) : null,
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
    private function read(LegalText $text, string $name, string $folder, string $language = 'en'): ?array
    {
        try {
            return $text->read($name, $language, $folder);
        } catch (RuntimeException) {
            // Listed but not written yet (a legal page still with the lawyer): not there.
            return null;
        }
    }
}
