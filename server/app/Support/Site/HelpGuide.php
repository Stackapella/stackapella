<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Http\Hosts;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The user guide at /help (docs/user-guide-plan.md): its groups and pages in order, which drive the
 * rail, the pager, the index, the search and the sitemap. A page's words are `pages.help.pages.<slug>`;
 * a page listed here without words yet isn't shown, so the guide grows a page at a time.
 *
 * A section is `[heading, items]`, with `'steps' => true` for a numbered list. An item is a line of
 * words (an app label in **bold**), `['shot' => '<id>', 'alt' => '...']` for a screenshot
 * (web/icons/help/<id>.png and <id>-dark.png, with webp), or `['tip' => '...']`.
 */
final class HelpGuide
{
    public const GROUPS = [
        'start' => ['getting-started'],
        'learn' => ['your-voice', 'learn-a-part', 'your-part-and-key'],
        'make' => ['studio', 'create'],
        'share' => ['sharing', 'groups'],
        'account' => ['plus-and-billing', 'settings', 'privacy', 'guides'],
        'fixes' => ['troubleshooting', 'mic', 'headphones', 'timing', 'iphone', 'install'],
    ];

    /** Old addresses, sent on to the page that took them over. */
    public const MOVED = ['first-harmony' => 'getting-started'];

    /** What comes up most, first on the index as big tiles, with their icons. */
    public const MOST_ASKED = ['mic' => 'mic', 'timing' => 'clock', 'headphones' => 'headphones'];

    /** Each group's icon on the index. */
    public const GROUP_ICONS = ['start' => 'spark', 'learn' => 'book', 'make' => 'mic', 'share' => 'link', 'account' => 'users', 'fixes' => 'info'];

    /** The "Start here" path on the index. */
    public const PATH = ['getting-started', 'learn-a-part', 'studio'];

    /**
     * Every page with words, in order.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_values(array_filter(array_merge(...array_values(self::GROUPS)), self::written(...)));
    }

    public static function written(string $slug): bool
    {
        return Lang::has('pages.help.pages.'.$slug.'.title');
    }

    /**
     * The groups with their written pages, for the rail and the index.
     *
     * @return list<array{key: string, title: string, pages: list<array{slug: string, title: string, summary: string}>}>
     */
    public static function groups(): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => $slugs) {
            $pages = array_map(self::card(...), array_values(array_filter($slugs, self::written(...))));
            if ($pages !== []) {
                $groups[] = ['key' => $key, 'title' => (string) __('pages.help.groups.'.$key), 'pages' => $pages];
            }
        }

        return $groups;
    }

    /**
     * @return array{slug: string, title: string, summary: string}
     */
    public static function card(string $slug): array
    {
        return ['slug' => $slug, 'title' => (string) __('pages.help.pages.'.$slug.'.title'), 'summary' => (string) __('pages.help.pages.'.$slug.'.summary')];
    }

    /**
     * The pages before and after this one.
     *
     * @return array{prev: array{slug: string, title: string, summary: string}|null, next: array{slug: string, title: string, summary: string}|null}
     */
    public static function neighbours(string $slug): array
    {
        $slugs = self::slugs();
        $at = array_search($slug, $slugs, true);
        if ($at === false) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $at > 0 ? self::card($slugs[$at - 1]) : null,
            'next' => $at < count($slugs) - 1 ? self::card($slugs[$at + 1]) : null,
        ];
    }

    /**
     * A page's sections, with the numbers filled in (`:price`, `:days`), each given an anchor and its
     * items grouped into blocks: a run of lines is one list (a numbered one keeps counting across a
     * screenshot or a tip), and a screenshot or a tip stands on its own.
     *
     * @param  array<string, string>  $fill
     * @return list<array{id: string, heading: string, steps: bool, fold: bool, blocks: list<array{type: string, items: list<string>, start: int, text: string, shot: array<string, array{png: string, webp: string, width: int, height: int}>|null, alt: string}>}>
     */
    public static function sections(string $slug, array $fill = []): array
    {
        $raw = (array) Lang::get('pages.help.pages.'.$slug.'.sections');
        // The mic page starts with the app's own mic dialog steps, so the two never differ.
        // Each device's steps fold away, so the page is one screen until you open yours.
        if ($slug === 'mic') {
            $mic = MicHelp::sections((string) __('pages.help.pages.mic.why'));
            $devices = array_map(static fn (array $section): array => [...$section, 'steps' => true, 'fold' => true], array_slice($mic, 1));
            $raw = [$mic[0], ...$devices, ...$raw];
        }

        $replace = array_combine(array_map(static fn (string $key): string => ':'.$key, array_keys($fill)), array_values($fill));
        $seen = [];
        $sections = [];
        foreach ($raw as $section) {
            $heading = (string) $section[0];
            $id = $base = Str::slug($heading) ?: 'section';
            for ($n = 2; isset($seen[$id]); $n++) {
                $id = $base.'-'.$n;
            }
            $seen[$id] = true;

            $blocks = [];
            $lines = [];
            $count = 0;
            foreach ([...(array) $section[1], null] as $item) {
                if (is_string($item)) {
                    $lines[] = strtr($item, $replace);
                    $count++;

                    continue;
                }
                // A screenshot, a tip or the end closes the run of lines before it.
                if ($lines !== []) {
                    $blocks[] = self::block('list', items: $lines, start: $count - count($lines) + 1);
                    $lines = [];
                }
                if (isset($item['tip'])) {
                    $blocks[] = self::block('tip', text: strtr((string) $item['tip'], $replace));
                } elseif (isset($item['links'])) {
                    $blocks[] = self::block('links', links: array_map(self::link(...), array_values((array) $item['links'])));
                } elseif (isset($item['shot']) && ($shot = self::shot((string) $item['shot'])) !== null) {
                    // A screenshot not yet shot is left out, so the words ship before the pictures.
                    $blocks[] = self::block('shot', shot: $shot, alt: (string) ($item['alt'] ?? ''));
                }
            }
            $sections[] = ['id' => $id, 'heading' => $heading, 'steps' => (bool) ($section['steps'] ?? false), 'fold' => (bool) ($section['fold'] ?? false), 'blocks' => $blocks];
        }

        return $sections;
    }

    /**
     * A screenshot's light and dark files (web/icons/help), or null until both are there; with the
     * serious skin's pair too ('serious', 'serious-dark') once all four of its files are there, so
     * a serious visitor sees the serious look.
     *
     * @return array<string, array{png: string, webp: string, width: int, height: int}>|null
     */
    public static function shot(string $id): ?array
    {
        $files = self::shotFiles($id, ['light' => '', 'dark' => '-dark']);

        if ($files === null) {
            return null;
        }

        return [...$files, ...(self::shotFiles($id, ['serious' => '-serious', 'serious-dark' => '-serious-dark']) ?? [])];
    }

    /**
     * The files of one pair of a screenshot's looks, or null when any is missing.
     *
     * @param  array<string, string>  $looks  each look's file suffix
     * @return array<string, array{png: string, webp: string, width: int, height: int}>|null
     */
    private static function shotFiles(string $id, array $looks): ?array
    {
        $files = [];
        foreach ($looks as $look => $suffix) {
            $path = '/icons/help/'.$id.$suffix;
            $png = self::web($path.'.png');
            $size = is_file($png) ? getimagesize($png) : false;
            if ($size === false || ! is_file(self::web($path.'.webp'))) {
                return null;
            }
            $files[$look] = ['png' => $path.'.png', 'webp' => $path.'.webp', 'width' => $size[0], 'height' => $size[1]];
        }

        return $files;
    }

    /**
     * Every screenshot the guide's words name.
     *
     * @return list<string>
     */
    public static function namedShots(): array
    {
        $ids = [];
        foreach (array_merge(...array_values(self::GROUPS)) as $slug) {
            foreach ((array) Lang::get('pages.help.pages.'.$slug.'.sections') as $section) {
                foreach ((array) ($section[1] ?? []) as $item) {
                    if (is_array($item) && isset($item['shot'])) {
                        $ids[] = (string) $item['shot'];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The search's rows: each page, and each of its sections, with its words.
     *
     * @param  array<string, string>  $fill
     * @return list<array{page: string, title: string, url: string, text: string}>
     */
    public static function searchIndex(array $fill = []): array
    {
        $rows = [];
        foreach (self::slugs() as $slug) {
            $card = self::card($slug);
            $rows[] = ['page' => $card['title'], 'title' => $card['title'], 'url' => '/help/'.$slug, 'text' => $card['summary']];
            foreach (self::sections($slug, $fill) as $section) {
                $words = [];
                foreach ($section['blocks'] as $block) {
                    $words = [...$words, ...$block['items'], $block['text']];
                }
                $rows[] = ['page' => $card['title'], 'title' => $section['heading'], 'url' => '/help/'.$slug.'#'.$section['id'], 'text' => Str::limit(str_replace('**', '', implode(' ', $words)), 300)];
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $items
     * @param  array<string, array{png: string, webp: string, width: int, height: int}>|null  $shot
     * @param  list<array{href: string, text: string, app: bool}>  $links
     * @return array{type: string, items: list<string>, start: int, text: string, shot: array<string, array{png: string, webp: string, width: int, height: int}>|null, alt: string, links: list<array{href: string, text: string, app: bool}>}
     */
    private static function block(string $type, array $items = [], int $start = 1, string $text = '', ?array $shot = null, string $alt = '', array $links = []): array
    {
        return ['type' => $type, 'items' => $items, 'start' => $start, 'text' => $text, 'shot' => $shot, 'alt' => $alt, 'links' => $links];
    }

    /**
     * Where an answer goes next: another page of the guide (`guide`, with `at` for one of its
     * sections, named with its heading), named by its title; or a screen of the app (`app`, its
     * path), named by `label`.
     *
     * @param  array{guide?: string, at?: string, app?: string, label?: string}  $link
     * @return array{href: string, text: string, app: bool}
     */
    private static function link(array $link): array
    {
        if (isset($link['app'])) {
            return ['href' => Hosts::app($link['app']), 'text' => (string) ($link['label'] ?? ''), 'app' => true];
        }
        $slug = (string) ($link['guide'] ?? '');
        if (! self::written($slug)) {
            throw new InvalidArgumentException("The guide has no page [{$slug}] to link to.");
        }

        $text = self::card($slug)['title'];
        if (isset($link['at'])) {
            $section = array_values(array_filter(self::sections($slug), static fn (array $s): bool => $s['id'] === $link['at']))[0]
                ?? throw new InvalidArgumentException("The guide's page [{$slug}] has no section [{$link['at']}].");
            $text .= ': '.$section['heading'];
        }

        return ['href' => '/help/'.$slug.(isset($link['at']) ? '#'.$link['at'] : ''), 'text' => $text, 'app' => false];
    }

    private static function web(string $path): string
    {
        return dirname(base_path()).'/web'.$path;
    }
}
