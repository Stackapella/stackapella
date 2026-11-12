<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Http\Hosts;

/**
 * What search engines read about the public site (schema.org JSON-LD): only what the page itself
 * says, prices only from config/billing.php, and nothing about any person.
 */
final class StructuredData
{
    /**
     * A course page: the course (its name and the page's own description, offered by Stackapella)
     * and the trail the page shows above its heading (Courses / the course).
     *
     * @param  array{title: string, description: string, url: string}  $meta
     * @return list<array<string, mixed>>
     */
    public static function course(string $name, array $meta): array
    {
        $site = Hosts::publicUrl().'/';

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $name,
                'description' => $meta['description'],
                'url' => $meta['url'],
                'inLanguage' => 'en',
                'provider' => ['@type' => 'Organization', 'name' => (string) __('meta.brand'), 'sameAs' => $site],
            ],
            self::breadcrumbs([[(string) __('pages.site.courses'), Hosts::publicUrl().'/courses'], [$name, $meta['url']]]),
        ];
    }

    /**
     * A free tool's page (docs/tools-plan.md): the tool as a free web app (any system, price 0, by Stackapella), its
     * questions as the page shows them, and the trail Free tools / the tool.
     *
     * @param  array{title: string, description: string, url: string}  $meta
     * @param  list<array{0: string, 1: string}>  $faq
     * @return list<array<string, mixed>>
     */
    public static function tool(string $name, array $meta, array $faq): array
    {
        $site = Hosts::publicUrl().'/';

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => $name,
                'description' => $meta['description'],
                'url' => $meta['url'],
                // Google's supported category for a music tool (docs/tools/seo.md); MusicApplication isn't one.
                'applicationCategory' => 'MultimediaApplication',
                'operatingSystem' => 'Any',
                'browserRequirements' => 'Requires a current web browser',
                'isAccessibleForFree' => true,
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                'publisher' => ['@type' => 'Organization', 'name' => (string) __('meta.brand'), 'url' => $site],
            ],
            ...($faq === [] ? [] : [[
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $faq),
            ]]),
            // The trail runs through the hub once it's live (SitePages::hubLive); before, the tool sits under the site.
            self::breadcrumbs([...(SitePages::hubLive() ? [[(string) __('pages.nav.tools'), Hosts::publicUrl().'/tools']] : []), [$name, $meta['url']]]),
        ];
    }

    /**
     * The tools' hub: its trail, and its tools as a list, each with its page.
     *
     * @param  list<array{name: string, url: string}>  $tools
     * @return list<array<string, mixed>>
     */
    public static function tools(string $name, string $url, array $tools): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $name,
                'itemListElement' => array_map(static fn (array $tool, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $tool['name'], 'url' => Hosts::publicUrl().$tool['url']], $tools, array_keys($tools)),
            ],
            self::breadcrumbs([[$name, $url]]),
        ];
    }

    /**
     * The Christmas carols page: its carols as a list, each with its song page, and the trail Songs / Christmas carols.
     *
     * @param  list<array{title: string, url: string}>  $carols
     * @return list<array<string, mixed>>
     */
    public static function carols(string $name, string $url, array $carols): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $name,
                'itemListElement' => array_map(static fn (array $carol, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $carol['title'], 'url' => Hosts::publicUrl().$carol['url']], $carols, array_keys($carols)),
            ],
            self::breadcrumbs([[(string) __('site.song_part.songs'), Hosts::publicUrl().'/songs'], [(string) __('site.carols.crumb'), $url]]),
        ];
    }

    /**
     * A song collection (/songs/<collection>, SongCollections): its songs as an ItemList, and the trail Songs, the
     * collection.
     *
     * @param  list<array{title: string, url: string}>  $songs
     * @return list<array<string, mixed>>
     */
    public static function collection(string $name, string $crumb, string $url, array $songs): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $name,
                'itemListElement' => array_map(static fn (array $song, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $song['title'], 'url' => Hosts::publicUrl().$song['url']], $songs, array_keys($songs)),
            ],
            self::breadcrumbs([[(string) __('site.song_part.songs'), Hosts::publicUrl().'/songs'], [$crumb, $url]]),
        ];
    }

    /**
     * The trail a page shows above its heading, as a list from the site's home: each step's name and
     * address, the last one this page.
     *
     * @param  list<array{0: string, 1: string}>  $steps
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $steps): array
    {
        $all = [[(string) __('meta.brand'), Hosts::publicUrl().'/'], ...$steps];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $step, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $step[0], 'item' => $step[1]], $all, array_keys($all)),
        ];
    }

    /**
     * The landing: who runs it, the site, the app with its free and Plus prices, and its questions.
     *
     * @param  array{currency: string, month: string, year: string}  $price
     * @param  list<array{0: string, 1: string}>  $faq
     * @return list<array<string, mixed>>
     */
    public static function landing(array $price, array $faq): array
    {
        $site = Hosts::publicUrl().'/';
        $amounts = (array) config('billing.amounts.'.$price['currency']);
        $currency = strtoupper($price['currency']);

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => __('meta.brand'),
                'url' => $site,
                'logo' => Hosts::public('/icons/icon-512.png'),
                'email' => (string) config('site.contact_email'),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => __('meta.brand'),
                'url' => $site,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => __('meta.brand'),
                'description' => __('pages.landing.description'),
                'url' => $site,
                'applicationCategory' => 'EducationalApplication',
                'operatingSystem' => 'Web browser',
                'offers' => [
                    ['@type' => 'Offer', 'name' => __('pages.pricing.free'), 'price' => '0', 'priceCurrency' => $currency],
                    ['@type' => 'Offer', 'name' => __('pages.pricing.plus'), 'price' => number_format((int) ($amounts['month'] ?? 0) / 100, 2, '.', ''), 'priceCurrency' => $currency],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $qa): array => [
                    '@type' => 'Question',
                    'name' => $qa[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
                ], $faq),
            ],
        ];
    }

    /**
     * A song's page: the composition, its parts' arrangement and whether it's free to learn; with its story, when the
     * page shows one (SongStories::story; the courses lead's guide, "Structured data"), the composition's makers, date,
     * other names and Wikidata item, the story as an Article citing its sources, and its questions as a FAQPage. Only
     * what the page shows: pass the story only where the page renders it.
     *
     * @param  array{title: string, description: string, pageUrl: string, genre: list<string>, credits: list<string>}  $song
     * @param  array<string, mixed>|null  $story
     * @return list<array<string, mixed>>
     */
    public static function song(array $song, ?array $story = null): array
    {
        $composition = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicComposition',
            'name' => $song['title'],
            'description' => $song['description'],
            'url' => $song['pageUrl'],
            'genre' => $song['genre'],
            'isAccessibleForFree' => true,
            'inLanguage' => app()->getLocale(),
        ];
        if ($story === null) {
            return [$composition];
        }

        $facts = (array) ($story['facts'] ?? []);
        // A maker named in the facts box, without the dates in brackets; "traditional" names no one.
        $person = static function (mixed $who): ?array {
            $name = trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $who));

            return $name === '' || preg_match('/^(traditional|tradicional|anonymous|anónimo)$/i', $name) === 1 ? null : ['@type' => 'Person', 'name' => $name];
        };
        $first = $story['timeline'][0]['year'] ?? null;
        $composition = array_filter([
            ...$composition,
            'lyricist' => $person($facts['words'] ?? null),
            'composer' => $person($facts['tune'] ?? null),
            'dateCreated' => is_string($first) && preg_match('/^\d{4}$/', $first) === 1 ? $first : null,
            'alternateName' => ($facts['alsoKnownAs'] ?? null) ?: null,
            'sameAs' => is_string($story['wikidata'] ?? null) ? 'https://www.wikidata.org/wiki/'.$story['wikidata'] : null,
            'musicArrangement' => ['@type' => 'MusicComposition', 'name' => $song['title'], 'arranger' => ['@type' => 'Organization', 'name' => 'Stackapella']],
            // The whole sung text, stanzas apart, where the story has the full words (and they aren't withheld here).
            'lyrics' => is_array($story['fullWords'] ?? null) ? array_filter([
                '@type' => 'CreativeWork',
                'text' => implode("\n\n", array_map(static fn (array $stanza): string => implode("\n", array_column($stanza['lines'], 'sung')), $story['fullWords']['stanzas'])),
                'inLanguage' => $story['sungLanguage'] ?? null,
            ], static fn (mixed $v): bool => $v !== null) : null,
        ], static fn (mixed $v): bool => $v !== null);

        $data = [$composition, [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => (string) $story['title'],
            'description' => (string) $story['description'],
            'url' => $song['pageUrl'],
            'mainEntityOfPage' => $song['pageUrl'],
            'about' => ['@type' => 'MusicComposition', 'name' => $song['title']],
            'inLanguage' => app()->getLocale(),
            'dateModified' => $story['updated'],
            'author' => ['@type' => 'Organization', 'name' => 'Stackapella', 'url' => Hosts::publicUrl()],
            'publisher' => ['@type' => 'Organization', 'name' => 'Stackapella', 'url' => Hosts::publicUrl()],
            'citation' => array_map(static fn (array $s): array => array_filter(['@type' => 'CreativeWork', 'name' => trim(html_entity_decode(strip_tags((string) $s['html']), ENT_QUOTES | ENT_HTML5)), 'url' => $s['url']]), (array) ($story['sources'] ?? [])),
        ]];
        // Each picture the page shows, with its credit and licence (the coordinator: ImageObject, creditText, license).
        foreach ((array) ($story['images'] ?? []) as $image) {
            $data[] = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'ImageObject',
                'contentUrl' => Hosts::publicUrl().$image['src'],
                'width' => $image['width'],
                'height' => $image['height'],
                'caption' => trim(html_entity_decode(strip_tags((string) $image['caption']), ENT_QUOTES | ENT_HTML5)),
                'creditText' => $image['credit'] !== '' ? $image['credit'] : null,
                'copyrightNotice' => $image['licenceName'],
                'license' => $image['licenceUrl'],
                'acquireLicensePage' => $image['sourceUrl'],
                'dateCreated' => $image['date'],
            ], static fn (mixed $v): bool => $v !== null && $v !== '');
        }
        if (($story['questions'] ?? []) !== []) {
            $data[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $q): array => ['@type' => 'Question', 'name' => $q['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['text']]], (array) $story['questions']),
            ];
        }

        return $data;
    }

    /**
     * A song's part page (/songs/<song>/<part>): the song as a MusicComposition with this part's page as its address
     * and the part named, and the trail Songs, the page that collects it (when one does), the song, the part.
     *
     * @param  array<string, mixed>  $page  SongParts::page()
     * @return list<array<string, mixed>>
     */
    public static function songPart(array $page, string $heading): array
    {
        $song = (array) $page['song'];
        $part = (array) $page['part'];

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'MusicComposition',
                'name' => $song['title'],
                'alternateName' => $heading,
                'description' => (string) ($song['about'] ?? null ?: $song['description']),
                'url' => $page['canonical'],
                'genre' => $song['genre'],
                'musicalKey' => $song['key'],
                'isAccessibleForFree' => (bool) $song['free'],
                'inLanguage' => app()->getLocale(),
            ],
            self::breadcrumbs([
                [(string) __('site.song_part.songs'), Hosts::publicUrl().'/songs'],
                // The page that collects it, as the trail on the page shows (SongCollections::home).
                ...(is_array($page['home'] ?? null) ? [[(string) $page['home']['name'], Hosts::publicUrl().$page['home']['path']]] : []),
                [(string) $song['title'], (string) $song['pageUrl']],
                [(string) $part['name'], (string) $page['canonical']],
            ]),
        ];
    }
}
