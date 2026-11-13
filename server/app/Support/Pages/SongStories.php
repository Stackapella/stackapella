<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A song's story (Hillel, 2026-11-12: "Make people come here instead of wikipeadia"; the courses lead's
 * docs/songs/story-guide.md, whose "Format" and "The parts of a story" this follows exactly): one Markdown file per
 * song beside the song files (content.stories: templates/library/stories/<slug>.md), each language a whole file
 * (<lang>/<slug>.md) shown only while its `translatedFrom` is the English file's hash.
 *
 * Front matter is flat `key: value` lines: title, description, lead, updated, checked, reviewed, searches, the facts
 * box (FACTS), wikidata, quote and quoteSource. The body is an opening paragraph, then `## ` sections, known by their
 * English headings (SECTIONS) and, in a translation, by their place among the English file's. `[^id]` cites a source
 * from `## Sources` (`- [^id]: ...`), which the page numbers. Only a reviewed story shows (story()); check() is the
 * content check.
 */
final class SongStories
{
    /** The sections by key, with their English headings, in their order; `required` ones every story has. */
    public const SECTIONS = [
        'origins' => ['Where it comes from', true],
        'words' => ['What the words mean', true],
        'sung' => ['How it is sung', true],
        'versions' => ['Versions and other words', false],
        'facts' => ['Did you know?', false],
        'singing' => ['Singing it', true],
        'questions' => ['Questions', false],
        'next' => ['Sing next', true],
        'sources' => ['Sources', true],
    ];

    /** The parser's version, in its cache key: a parse kept from an older one is never served (2: fullWords; 3: plain text's entities read back). */
    private const PARSER = 3;

    /** The words section's last subsection, the song's whole text, by the story's language (marketing's fullWords). */
    public const FULL_WORDS = ['The full words', 'La letra completa'];

    /** A language's code from its name in the front matter (English or Spanish), for `scriptLang` and the lyrics' inLanguage. */
    private const LANGUAGES = [
        'english' => 'en', 'inglés' => 'en', 'spanish' => 'es', 'español' => 'es', 'latin' => 'la', 'latín' => 'la',
        'german' => 'de', 'alemán' => 'de', 'french' => 'fr', 'francés' => 'fr', 'italian' => 'it', 'italiano' => 'it',
        'welsh' => 'cy', 'galés' => 'cy', 'irish' => 'ga', 'irlandés' => 'ga', 'hebrew' => 'he', 'hebreo' => 'he',
        'yiddish' => 'yi', 'ídish' => 'yi', 'yidis' => 'yi', 'russian' => 'ru', 'ruso' => 'ru', 'ukrainian' => 'uk',
        'ucraniano' => 'uk', 'greek' => 'el', 'griego' => 'el', 'japanese' => 'ja', 'japonés' => 'ja', 'chinese' => 'zh',
        'chino' => 'zh', 'korean' => 'ko', 'coreano' => 'ko', 'arabic' => 'ar', 'árabe' => 'ar', 'georgian' => 'ka',
        'georgiano' => 'ka', 'armenian' => 'hy', 'armenio' => 'hy', 'zulu' => 'zu', 'swahili' => 'sw', 'suajili' => 'sw',
    ];

    /** The quick-facts box's fields, in the order the page lists them. */
    public const FACTS = ['words', 'tune', 'tuneName', 'meter', 'earliestPrinting', 'language', 'origin', 'occasion', 'alsoKnownAs'];

    public const DESCRIPTION_MAX = 160;

    public const LEAD_WORDS = 40;

    public const MIN_SOURCES = 3;

    /**
     * The story to show on a song's page, in the page's language when its translation is current, else English; null
     * when there's none, or it isn't reviewed yet.
     *
     * @return array<string, mixed>|null see parse()
     */
    public static function story(string $slug): ?array
    {
        $story = self::read($slug, App::getLocale());

        return $story !== null && $story['reviewed'] ? $story : null;
    }

    /** The day the shown story last changed (the sitemap's lastmod), or null. */
    public static function updated(string $slug): ?string
    {
        $story = self::story($slug);

        return is_string($story['updated'] ?? null) ? $story['updated'] : null;
    }

    /** @return list<string> the songs with a story file in English */
    public static function slugs(): array
    {
        return array_values(array_map(static fn (string $file): string => basename($file, '.md'), glob(self::root().'/*.md') ?: []));
    }

    /**
     * A story in a language (English when that language's file is missing or stale), parsed, reviewed or not.
     *
     * @return array<string, mixed>|null see parse()
     */
    public static function read(string $slug, string $language = 'en'): ?array
    {
        $english = self::path($slug);
        if ($english === null) {
            return null;
        }
        $file = $english;
        if ($language !== 'en' && preg_match('/^[a-z]{2}$/', $language) === 1 && is_file($translated = self::root().'/'.$language.'/'.$slug.'.md')
            && (self::split((string) file_get_contents($translated))[0]['translatedFrom'] ?? null) === self::hash($english)) {
            $file = $translated;
        }
        $text = (string) file_get_contents($file);
        $keys = $file === $english ? null : self::keys((string) file_get_contents($english));

        // Parsed once per version of the file (its hash is in the key), on the file store: never the database.
        return Cache::store('file')->rememberForever('song-story:'.self::PARSER.':'.$slug.':'.sha1($text.json_encode($keys)), static fn (): array => self::parse($slug, $file === $english ? 'en' : $language, $text, $keys));
    }

    /** The first 12 hex of a file's sha256: what a translation names in `translatedFrom`. */
    public static function hash(string $path): string
    {
        return substr((string) hash_file('sha256', $path), 0, 12);
    }

    /**
     * What the content check finds wrong with a song's story, English and each translation, one line each.
     *
     * @return list<string>
     */
    public static function check(string $slug): array
    {
        $english = self::path($slug);
        if ($english === null) {
            return [$slug.': no story'];
        }
        $text = (string) file_get_contents($english);
        $problems = self::checkText($slug, 'en', $text, null);
        [$front, $body] = self::split($text);
        $placed = array_values(array_unique([...self::placed($body), ...(isset($front['hero']) && $front['hero'] !== '' ? [$front['hero']] : [])]));
        $languages = ['en', ...array_map(static fn (string $f): string => basename(dirname($f)), glob(self::root().'/*/'.$slug.'.md') ?: [])];
        if ($placed !== [] || is_dir(self::root().'/media/'.$slug)) {
            $problems = [...$problems, ...SongMedia::check($slug, $placed, array_values(array_diff($languages, ['media'])))];
        }
        foreach (['listen', 'original'] as $key) {
            if (($front[$key] ?? '') !== '' && count(self::listen($front[$key])) !== count(array_filter(explode('||', $front[$key]), static fn (string $l): bool => trim($l) !== ''))) {
                $problems[] = $slug.': a '.$key.' link isn\'t "Label | https://..."';
            }
        }
        $ids = array_keys(self::sources(self::split($text)[1]));

        foreach (glob(self::root().'/*/'.$slug.'.md') ?: [] as $translation) {
            $language = basename(dirname($translation));
            $words = (string) file_get_contents($translation);
            $problems = [...$problems, ...self::checkText($slug, $language, $words, self::keys($text))];
            if (array_keys(self::sources(self::split($words)[1])) !== $ids) {
                $problems[] = $slug.' ('.$language.'): its source ids differ from the English';
            }
            if (! isset(self::split($words)[0]['translatedFrom'])) {
                $problems[] = $slug.' ('.$language.'): no translatedFrom';
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>|null  $keys  a translation's section keys, from its English file's order
     * @return list<string>
     */
    private static function checkText(string $slug, string $language, string $text, ?array $keys): array
    {
        $at = $slug.($language === 'en' ? '' : ' ('.$language.')').': ';
        $problems = [];
        [$front, $body] = self::split($text);

        foreach (['title', 'description', 'lead', 'updated', 'checked', 'reviewed'] as $key) {
            if (($front[$key] ?? '') === '') {
                $problems[] = $at.'no '.$key;
            }
        }
        // A front matter line is split on its first colon and taken as it is, so quotes around a whole value would be
        // shown (they reached 97 headlines once); a value that only contains quotes, around a title, is fine.
        foreach ($front as $key => $value) {
            if (preg_match('/^"[^"]*"$/', $value) === 1) {
                $problems[] = $at.$key.' is in quotes: front matter values aren\'t quoted (the line splits on its first colon)';
            }
        }
        foreach (['updated', 'checked'] as $key) {
            if (isset($front[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $front[$key]) !== 1) {
                $problems[] = $at.$key.' is not a date (YYYY-MM-DD)';
            }
        }
        if (mb_strlen($front['description'] ?? '') > self::DESCRIPTION_MAX) {
            $problems[] = $at.'its description is over '.self::DESCRIPTION_MAX.' characters';
        }
        // Words in any language: str_word_count() splits a word at each accented letter ("Janucá" would be two).
        if (preg_match_all('/[\p{L}\p{M}\'’]+/u', $front['lead'] ?? '') >= self::LEAD_WORDS) {
            $problems[] = $at.'its lead is '.self::LEAD_WORDS.' words or more';
        }
        if (str_contains($text, "\u{2014}")) {
            $problems[] = $at.'an em dash';
        }

        $sections = self::sections($body);
        $found = $keys ?? array_map(static fn (array $s): string => self::keyOf($s['heading']) ?? '?'.$s['heading'], $sections);
        if ($keys !== null && count($keys) !== count($sections)) {
            $problems[] = $at.'it has '.count($sections).' sections, the English '.count($keys);
        }
        foreach ($found as $key) {
            if (str_starts_with($key, '?')) {
                $problems[] = $at.'"'.substr($key, 1).'" is not one of the guide\'s sections';
            }
        }
        $order = array_keys(self::SECTIONS);
        $known = array_values(array_filter($found, static fn (string $k): bool => isset(self::SECTIONS[$k])));
        $sorted = $known;
        usort($sorted, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));
        if ($known !== $sorted) {
            $problems[] = $at.'its sections are out of the guide\'s order';
        }
        foreach (self::SECTIONS as $key => [$heading, $required]) {
            if ($required && ! in_array($key, $found, true)) {
                $problems[] = $at.'no "'.$heading.'"';
            }
        }
        if (trim(self::opening($body)) === '') {
            $problems[] = $at.'no opening paragraph';
        }
        foreach ($sections as $section) {
            if (trim($section['body']) === '') {
                $problems[] = $at.'"'.$section['heading'].'" is empty';
            }
        }

        $byKey = self::byKey($sections, $found);
        if (isset($byKey['origins']) && self::timeline($byKey['origins']) === []) {
            $problems[] = $at.'"'.self::headingOf($sections, $found, 'origins').'" has no ### Timeline rows (- <year>: ...)';
        }
        foreach (isset($byKey['origins']) ? self::timelineRows($byKey['origins']) : [] as $row) {
            if (self::when($row) === null) {
                $problems[] = $at.'a Timeline row with no date before its ": ": "'.mb_substr($row, 0, 60).'"';
            }
        }
        if (isset($byKey['next']) && self::next($byKey['next']) === []) {
            $problems[] = $at.'"'.self::headingOf($sections, $found, 'next').'" has no - [Title](/songs/<slug>): reason rows';
        }

        $sources = self::sources($body);
        if (count($sources) < self::MIN_SOURCES) {
            $problems[] = $at.'fewer than '.self::MIN_SOURCES.' sources';
        }
        if (isset($front['earliestPrintingSource']) && ! isset($sources[$front['earliestPrintingSource']])) {
            $problems[] = $at.'earliestPrintingSource '.$front['earliestPrintingSource'].' is not a source';
        }
        $prose = self::opening($body)."\n".implode("\n", array_map(static fn (array $s): string => $s['body'], array_filter($sections, static fn (array $s): bool => self::sources($s['body']) === [])));
        preg_match_all('/\[\^([A-Za-z0-9_-]+)\]/', $prose, $cited);
        $cited = array_values(array_unique([...$cited[1], ...(isset($front['earliestPrintingSource']) ? [$front['earliestPrintingSource']] : [])]));
        foreach (array_diff($cited, array_keys($sources)) as $id) {
            $problems[] = $at.'[^'.$id.'] has no source';
        }
        foreach (array_diff(array_keys($sources), $cited) as $id) {
            $problems[] = $at.'source [^'.$id.'] is never cited';
        }
        if (preg_match('#https?://#i', $prose.' '.implode(' ', array_diff_key($front, ['wikidata' => 1, 'listen' => 1, 'original' => 1]))) === 1) {
            $problems[] = $at.'an address outside Sources';
        }

        return $problems;
    }

    /**
     * @param  list<string>|null  $keys
     * @return array<string, mixed>
     */
    private static function parse(string $slug, string $language, string $text, ?array $keys): array
    {
        [$front, $body] = self::split($text);
        $sources = self::sources($body);
        $numbers = array_flip(array_keys($sources));
        $cite = static fn (string $html): string => (string) preg_replace_callback('/\[\^([A-Za-z0-9_-]+)\]/', static fn (array $m): string => isset($numbers[$m[1]])
            ? '<sup class="s-cite"><a href="#source-'.($numbers[$m[1]] + 1).'">['.($numbers[$m[1]] + 1).']</a></sup>'
            : '', $html);
        // A picture placed on a line of its own (`![[1]]`) is a placeholder the page splits on (`<!--figure:1-->`), never
        // in a paragraph; the hero's placement is dropped, as the page draws it by the heading (marketing, 2026-11-12).
        $hero = preg_match('/^[a-z0-9-]+$/', (string) ($front['hero'] ?? '')) === 1 ? (string) $front['hero'] : null;
        $figures = static fn (string $html): string => (string) preg_replace_callback('#<p>!\[\[([a-z0-9-]+)\]\]</p>\n?#', static fn (array $m): string => $m[1] === $hero ? '' : '<!--figure:'.$m[1].'-->'."\n", $html);
        // Plain text: the markdown's HTML without its tags, and its entities (&quot;, &amp;) read back as the characters
        // they stand for, since every use prints or encodes it again (a view's {{ }}, JSON-LD).
        $plain = static fn (string $markdown): string => trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) preg_replace('/\[\^[A-Za-z0-9_-]+\]/', '', self::markdown($markdown))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $sections = self::sections($body);
        $found = $keys ?? array_map(static fn (array $s): string => self::keyOf($s['heading']) ?? '?', $sections);
        $parts = [];
        $questions = [];
        $timeline = [];
        $facts = [];
        $next = [];
        $fullWords = null;
        foreach ($sections as $i => $section) {
            $key = $found[$i] ?? '?';
            if ($key === 'sources') {
                continue;
            }
            $html = '';
            if ($key === 'questions') {
                foreach (self::questions($section['body']) as $q) {
                    $questions[] = ['question' => $q['question'], 'html' => $cite(self::markdown($q['answer'])), 'text' => $plain($q['answer'])];
                }
            } elseif ($key === 'next') {
                $next = self::next($section['body']);
            } elseif ($key === 'facts') {
                $facts = array_map(static fn (string $row): string => $cite(self::inline($row)), self::rows($section['body']));
            } else {
                $prose = $section['body'];
                if ($key === 'origins') {
                    $timeline = array_map(static fn (array $row): array => ['year' => $row['year'], 'html' => $cite(self::inline($row['line']))], self::timeline($prose));
                    $prose = self::before('/^### /m', $prose);
                }
                if ($key === 'words') {
                    [$prose, $fullWords] = self::fullWords($prose, self::languageCode((string) ($front['language'] ?? '')), $cite, $plain);
                }
                $html = $figures($cite(self::markdown($prose)));
            }
            $parts[] = ['key' => $key, 'id' => Str::slug($section['heading']) ?: $key, 'heading' => $section['heading'], 'html' => $html];
        }

        $list = [];
        foreach (array_keys($sources) as $n => $id) {
            $citation = $sources[$id];
            $url = preg_match('#\s(https?://\S+)\s*$#', $citation, $m) === 1 ? rtrim($m[1], '.,;') : null;
            $words = $url !== null ? trim(substr($citation, 0, -strlen($m[0]))) : $citation;
            $list[] = ['id' => $id, 'n' => $n + 1, 'html' => self::inline($words), 'url' => $url];
        }

        $box = [];
        foreach (self::FACTS as $field) {
            if (($front[$field] ?? '') !== '') {
                $box[$field] = $front[$field];
            }
        }
        $printedIn = $front['earliestPrintingSource'] ?? null;

        return [
            'slug' => $slug,
            'language' => $language,
            'reviewed' => ($front['reviewed'] ?? '') === 'true',
            'title' => (string) ($front['title'] ?? ''),
            'description' => (string) ($front['description'] ?? ''),
            'lead' => (string) ($front['lead'] ?? ''),
            'updated' => self::date($front['updated'] ?? null),
            'checked' => self::date($front['checked'] ?? null),
            'searches' => array_values(array_filter(array_map('trim', explode('|', (string) ($front['searches'] ?? ''))))),
            'facts' => $box,
            'earliestPrintingSource' => is_string($printedIn) && isset($numbers[$printedIn]) ? $numbers[$printedIn] + 1 : null,
            'wikidata' => preg_match('/^Q\d+$/', (string) ($front['wikidata'] ?? '')) === 1 ? (string) $front['wikidata'] : null,
            'quote' => ($front['quote'] ?? '') !== '' ? ['text' => (string) $front['quote'], 'source' => (string) ($front['quoteSource'] ?? '')] : null,
            'opening' => $figures($cite(self::markdown(self::opening($body)))),
            'hero' => $hero,
            'images' => SongMedia::forPage($slug, array_values(array_unique([...($hero !== null ? [$hero] : []), ...self::placed($body)])), $language, static fn (string $caption): string => $cite(self::inline($caption))),
            'listen' => self::listen((string) ($front['listen'] ?? '')),
            // Primary scans that aren't among the pictures (Mohr's autograph): "See the original" links, as listen.
            'original' => self::listen((string) ($front['original'] ?? '')),
            'sections' => $parts,
            // The song's whole text, out of its words section (null when it has none, or where its words are withheld:
            // withoutWords()); the page falls back to today's verses.
            'fullWords' => $fullWords,
            'sungLanguage' => self::languageCode((string) ($front['language'] ?? '')),
            'timeline' => $timeline,
            'didYouKnow' => $facts,
            'questions' => $questions,
            'next' => $next,
            'sources' => $list,
        ];
    }

    /**
     * A story without the song's words, where they're withheld (docs/geo-rights-plan.md): the full words go (they're
     * already out of the words section's html).
     *
     * @param  array<string, mixed>  $story
     * @return array<string, mixed>
     */
    public static function withoutWords(array $story): array
    {
        return ['fullWords' => null] + $story;
    }

    /**
     * The words section's "### The full words" (or its translation), when it's the section's last subsection: the prose
     * without it, and the full words (marketing's shape, agreed 2026-11-12): an intro paragraph with its citations, then
     * stanzas split by blank lines, each with an optional `**Verse 1**` label. A line is sung as written; `> original`
     * then its meaning; or `> script`, `*transliteration*`, then its meaning.
     *
     * @param  callable(string): string  $cite
     * @param  callable(string): string  $plain
     * @return array{0: string, 1: array{intro: string, stanzas: list<array{label: string|null, lines: list<array<string, string>>}>}|null}
     */
    private static function fullWords(string $prose, ?string $language, callable $cite, callable $plain): array
    {
        if (preg_match_all('/^### (.+)$/m', $prose, $headings, PREG_OFFSET_CAPTURE) < 1) {
            return [$prose, null];
        }
        $last = $headings[1][count($headings[1]) - 1];
        if (! in_array(trim($last[0]), self::FULL_WORDS, true)) {
            return [$prose, null];
        }
        $start = $headings[0][count($headings[0]) - 1][1];
        $block = trim(substr($prose, $start + strlen($headings[0][count($headings[0]) - 1][0])));
        $prose = rtrim(substr($prose, 0, $start));

        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $block) ?: []), static fn (string $p): bool => $p !== ''));
        $intro = $paragraphs !== [] && ! preg_match('/^(>|\*\*)/', $paragraphs[0]) ? array_shift($paragraphs) : '';
        $stanzas = [];
        foreach ($paragraphs as $paragraph) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $paragraph)), static fn (string $l): bool => $l !== ''));
            $label = null;
            if ($lines !== [] && preg_match('/^\*\*(.+)\*\*$/', $lines[0], $m) === 1) {
                $label = $plain($m[1]);
                array_shift($lines);
            }
            $out = [];
            for ($i = 0; $i < count($lines); $i++) {
                if (! str_starts_with($lines[$i], '>')) {
                    $out[] = ['sung' => $plain($lines[$i])];

                    continue;
                }
                $original = $plain(ltrim(substr($lines[$i], 1)));
                $translit = null;
                if (isset($lines[$i + 1]) && preg_match('/^\*([^*].*?)\*$/', $lines[$i + 1], $m) === 1) {
                    $translit = $plain($m[1]);
                    $i++;
                }
                $line = $translit === null ? ['sung' => $original] : ['script' => $original, ...($language !== null ? ['scriptLang' => $language] : []), 'sung' => $translit];
                if (isset($lines[$i + 1]) && ! str_starts_with($lines[$i + 1], '>')) {
                    $line['meaning'] = $plain($lines[$i + 1]);
                    $i++;
                }
                $out[] = $line;
            }
            if ($out !== []) {
                $stanzas[] = ['label' => $label, 'lines' => $out];
            }
        }

        return [$prose, $stanzas === [] ? null : ['intro' => $intro === '' ? '' : $cite(self::markdown($intro)), 'stanzas' => $stanzas]];
    }

    private static function languageCode(string $name): ?string
    {
        return self::LANGUAGES[mb_strtolower(trim($name))] ?? null;
    }

    /** @return list<string> the pictures a story places, by name, in order (`![[1]]` on a line of its own) */
    private static function placed(string $body): array
    {
        preg_match_all('/^!\[\[([a-z0-9-]+)\]\]\s*$/m', $body, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return list<array{label: string, url: string}> front matter `listen: Label | URL || Label | URL` */
    private static function listen(string $value): array
    {
        $links = [];
        foreach (array_filter(array_map('trim', explode('||', $value))) as $link) {
            [$label, $url] = array_map('trim', explode('|', $link, 2)) + [1 => ''];
            if ($label !== '' && preg_match('#^https://\S+$#', $url) === 1) {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    private static function path(string $slug): ?string
    {
        $path = self::root().'/'.$slug.'.md';

        return preg_match('/^[a-z0-9-]+$/', $slug) === 1 && is_file($path) ? $path : null;
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /** @return array{0: array<string, string>, 1: string} the flat front matter and the body */
    private static function split(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $text, $m) !== 1) {
            return [[], $text];
        }
        $front = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^([A-Za-z]+):\s*(.*)$/', $line, $kv) === 1) {
                $front[$kv[1]] = trim($kv[2]);
            }
        }

        return [$front, $m[2]];
    }

    /** The paragraph before the first section. */
    private static function opening(string $body): string
    {
        return self::before('/^## /m', $body);
    }

    /** The text before a pattern's first match (all of it when there's none). */
    private static function before(string $pattern, string $text): string
    {
        $parts = preg_split($pattern, $text, 2);

        return trim(is_array($parts) ? $parts[0] : $text);
    }

    /** @return list<array{heading: string, body: string}> */
    private static function sections(string $body): array
    {
        $parts = preg_split('/^## +(.+)$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $sections = [];
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $sections[] = ['heading' => trim($parts[$i]), 'body' => trim($parts[$i + 1])];
        }

        return $sections;
    }

    private static function keyOf(string $heading): ?string
    {
        foreach (self::SECTIONS as $key => [$english]) {
            if (strcasecmp($english, $heading) === 0) {
                return $key;
            }
        }

        return null;
    }

    /** @return list<string> the English file's section keys, in order: a translation's sections are these, in place */
    private static function keys(string $english): array
    {
        return array_map(static fn (array $s): string => self::keyOf($s['heading']) ?? '?', self::sections(self::split($english)[1]));
    }

    /**
     * @param  list<array{heading: string, body: string}>  $sections
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function byKey(array $sections, array $keys): array
    {
        $out = [];
        foreach ($sections as $i => $section) {
            $out[$keys[$i] ?? '?'] = $section['body'];
        }

        return $out;
    }

    /**
     * @param  list<array{heading: string, body: string}>  $sections
     * @param  list<string>  $keys
     */
    private static function headingOf(array $sections, array $keys, string $key): string
    {
        $i = array_search($key, $keys, true);

        return is_int($i) ? $sections[$i]['heading'] : self::SECTIONS[$key][0];
    }

    /** @return array<string, string> each source's id and its citation, in the list's order */
    private static function sources(string $body): array
    {
        preg_match_all('/^- \[\^([A-Za-z0-9_-]+)\]:\s*(.+)$/m', $body, $m, PREG_SET_ORDER);

        return array_column(array_map(static fn (array $x): array => [$x[1], trim($x[2])], $m), 1, 0);
    }

    /** @return list<array{year: string, line: string}> the `### ` Timeline's `- <when>: <line>` rows */
    private static function timeline(string $origins): array
    {
        $rows = [];
        foreach (self::timelineRows($origins) as $row) {
            $when = self::when($row);
            if ($when !== null) {
                $rows[] = ['year' => $when, 'line' => trim(substr($row, strlen($when) + 1))];
            }
        }

        return $rows;
    }

    /** @return list<string> every `- ` row under the origins section's `### ` Timeline, parsed or not */
    private static function timelineRows(string $origins): array
    {
        $after = preg_split('/^### .*$/m', $origins, 2);
        preg_match_all('/^- (.+)$/m', (string) ($after[1] ?? ''), $m);

        return array_map('trim', $m[1]);
    }

    /**
     * A row's date, the text before its first ": " when it reads as one (the courses lead, 2026-11-12): under 40
     * characters, no citation, and a digit or a Spanish century ("siglo"). "1583/4", "About 1505", "1913 or 1914",
     * "9th century BCE", "hacia 1846 a 1850", "finales del siglo XII" and "década de 1810" all are.
     */
    private static function when(string $row): ?string
    {
        $at = strpos($row, ': ');
        if ($at === false) {
            return null;
        }
        $when = substr($row, 0, $at);

        return mb_strlen($when) < 40 && ! str_contains($when, '[') && preg_match('/\d|siglo/iu', $when) === 1 ? $when : null;
    }

    /** @return list<array{slug: string, title: string, reason: string}> */
    private static function next(string $body): array
    {
        preg_match_all('#^- \[([^\]]+)\]\(/songs/([a-z0-9-]+)\):\s*(.+)$#m', $body, $m, PREG_SET_ORDER);

        return array_map(static fn (array $x): array => ['slug' => $x[2], 'title' => trim($x[1]), 'reason' => trim($x[3])], $m);
    }

    /** @return list<string> a section's `- ` rows */
    private static function rows(string $body): array
    {
        preg_match_all('/^- (.+)$/m', $body, $m);

        return array_map('trim', $m[1]);
    }

    /** @return list<array{question: string, answer: string}> */
    private static function questions(string $body): array
    {
        $parts = preg_split('/^### +(.+)$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $questions = [];
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            if (trim($parts[$i + 1]) !== '') {
                $questions[] = ['question' => trim($parts[$i]), 'answer' => trim($parts[$i + 1])];
            }
        }

        return $questions;
    }

    /** Markdown to HTML, our own text only: raw HTML stripped and unsafe links refused. */
    private static function markdown(string $text): string
    {
        return Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /** One line of Markdown, without its paragraph. */
    private static function inline(string $text): string
    {
        return trim((string) preg_replace('#^<p>(.*)</p>$#s', '$1', trim(self::markdown($text))));
    }

    private static function root(): string
    {
        return (string) config('content.stories');
    }
}
