<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Http\StaticVersions;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A public page's stylesheets, only the rules its page family can use (docs/css-split-plan.md, step 4). css/site.css and
 * css/site-game.css are every public page's rules, about 62 and 19 KB gz, render-blocking; a family (the landing, the
 * songs, the tools, the reference pages, the courses, the rest) uses about half. The layout links
 * /site-css/<family>.css?v=<version> and names /site-css/<family>-game.css in its game-css meta (Public\SiteCssController):
 * the whole sheet less every rule none of whose selectors could match with the classes the family can show, in the
 * sheet's own order, each in its @media. Nothing that could match is left out and nothing moves, so a page looks exactly
 * as with the whole sheet.
 *
 * What a family can show: the classes of every page of it as rendered (server/resources/site-css-families.json, written
 * by web/tool/site_families.py from a running site), every word of the site's chrome (the layout, the header and the
 * footer, and the site components they use: what they show only now and then, as the language switcher), and every word
 * of the JS the public pages load, followed through its imports (a module can write any class; a word ending in "-"
 * before a template's ${} or a quote is a prefix). A selector needs a class only outside :not(), :is() and :where() and
 * outside an attribute selector. Rules naming no class, @keyframes, @font-face and @property stay.
 *
 * The safety: a page that shows a class some selector needs and its family's manifest lacks (a new collection's tone, a
 * page added since the manifest was written) gets the whole site.css and site-game.css, as before; tests/Feature/Site/SiteCssPagesTest fails
 * on it, naming the command that writes the manifest again. So a stale manifest costs bytes, never looks.
 */
final class SiteCss
{
    public const WHOLE = '/css/site.css';

    public const GAME = '/css/site-game.css';

    /** Each family, and the first segments of the paths it serves (a language's /es/ left off); any other page is info. */
    public const FAMILIES = [
        'landing' => [''],
        'songs' => ['songs', 'songbook'],
        'tools' => ['tools', 'warmups'],
        'reference' => ['intervals', 'chords', 'voice-types', 'note-frequencies'],
        'courses' => ['courses'],
        'info' => [],
    ];

    public const MANIFEST = 'site-css-families.json';

    private const BLOCKS = ['@media', '@supports', '@container', '@layer'];

    /** What a page's markup names as a class: every word of each class="..." or class='...'. */
    private const CLASS_ATTR = '/(?<=\s)class=(?:"([^"]*)"|\'([^\']*)\')/';

    /** @var array<string, array{styled: array<string, true>, words: array<string, true>, hash: string}> */
    private static array $known = [];

    /** @var array<string, string> */
    private static array $versions = [];

    /** @var array<string, string> */
    private static array $newest = [];

    /** @var array<string, string> the two sheets' hashes, once a request */
    private static array $sheets = [];

    /** @var array<string, array<string, array<string, true>>> */
    private static array $manifest = [];

    /** The family of a public page's path ("/es/songs/x" is songs). */
    public static function family(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        if (count($segments) > 1 && array_key_exists($segments[0], (array) config('languages.site'))) {
            array_shift($segments);
        }

        foreach (self::FAMILIES as $family => $firsts) {
            if (in_array($segments[0], $firsts, true)) {
                return $family;
            }
        }

        return array_key_exists($segments[0], (array) config('languages.site')) ? 'landing' : 'info';
    }

    /**
     * The page's two sheets: [its stylesheet link, its game sheet's], its family's when every class the page shows is one
     * its family can, else (or if anything fails) the whole site.css and site-game.css.
     *
     * @return array{0: string, 1: string}
     */
    public static function urls(string $webPath, string $path, string $html): array
    {
        $whole = [StaticVersions::url($webPath, self::WHOLE), StaticVersions::url($webPath, self::GAME)];

        try {
            $family = self::family($path);
            $version = self::version($webPath);

            if ($version === '' || self::missing($webPath, $family, $html) !== []) {
                return $whole;
            }

            return ['/site-css/'.$family.'.css?v='.$version, '/site-css/'.$family.'-game.css?v='.$version];
        } catch (Throwable $error) {
            report($error);

            return $whole;
        }
    }

    /**
     * The classes the page's markup shows that its family can't (none: its family's sheets serve it exactly).
     *
     * @return list<string>
     */
    public static function missing(string $webPath, string $family, string $html): array
    {
        $manifest = self::manifest()[$family] ?? [];
        $known = self::known($webPath);

        // A class no selector needs can't change what the page looks like, whatever sheet it gets.
        return array_values(array_filter(self::shown($html), static fn (string $class): bool => isset($known['styled'][$class]) && ! isset($manifest[$class]) && ! isset($known['words'][$class])));
    }

    /**
     * Every class the markup names, each once.
     *
     * @return list<string>
     */
    public static function shown(string $html): array
    {
        preg_match_all(self::CLASS_ATTR, $html, $m);
        $words = preg_split('/\s+/', implode(' ', [...$m[1], ...$m[2]]), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map(static fn (string $w): string => html_entity_decode($w, ENT_QUOTES | ENT_HTML5), $words === false ? [] : $words)));
    }

    /**
     * The version both of a family's sheets are asked for with: the whole sheets', the manifest's and what the layout and
     * the public JS can show ('' when a sheet is missing).
     */
    public static function version(string $webPath): string
    {
        $site = StaticVersions::of($webPath, self::WHOLE);
        $game = StaticVersions::of($webPath, self::GAME);

        if ($site === '' || $game === '') {
            return '';
        }

        return self::$versions[$site.$game] ??= substr(hash('xxh128', $site.$game.self::fingerprint().self::known($webPath)['hash']), 0, 12);
    }

    /** The family's sheet: site.css (or site-game.css) less the rules it can't use, in its order. */
    public static function sheet(string $webPath, string $family, bool $game): string
    {
        $path = rtrim($webPath, '/').($game ? self::GAME : self::WHOLE);
        $css = is_file($path) ? (string) file_get_contents($path) : '';
        $classes = self::manifest()[$family] ?? [];
        $known = self::known($webPath);
        $can = static fn (string $class): bool => isset($classes[$class]) || isset($known['words'][$class]);

        return self::cut((string) preg_replace('#/\*.*?\*/#s', '', $css), $can);
    }

    /** The manifest's content, hashed (part of the sheets' version). */
    public static function fingerprint(): string
    {
        $path = resource_path(self::MANIFEST);

        return is_file($path) ? (string) hash_file('xxh128', $path) : '';
    }

    /**
     * Each family's classes, from the manifest.
     *
     * @return array<string, array<string, true>>
     */
    public static function manifest(): array
    {
        $path = resource_path(self::MANIFEST);
        $key = is_file($path) ? $path.'@'.filemtime($path) : '';

        if (! isset(self::$manifest[$key])) {
            $data = $key === '' ? [] : json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
            $families = is_array($data) && is_array($data['families'] ?? null) ? $data['families'] : [];
            $out = [];

            foreach ($families as $family => $classes) {
                $out[(string) $family] = array_fill_keys(array_map('strval', (array) $classes), true);
            }

            self::$manifest[$key] = $out;
        }

        return self::$manifest[$key];
    }

    /**
     * The classes a selector needs: those outside :not(), :is() and :where() and outside an attribute selector, unescaped.
     *
     * @return list<string>
     */
    public static function required(string $selector): array
    {
        $out = [];
        $n = strlen($selector);

        for ($i = 0; $i < $n; $i++) {
            $c = $selector[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '[') {
                $i = self::closing($selector, $i, '[', ']');
            } elseif ($c === ':' && preg_match('/\G:(?:not|is|where)\(/', $selector, $m, 0, $i) === 1) {
                $i = self::closing($selector, $i + strlen($m[0]) - 1, '(', ')');
            } elseif ($c === '.') {
                $name = '';

                for ($i++; $i < $n; $i++) {
                    $d = $selector[$i];

                    if ($d === '\\' && preg_match('/\G\\\\([0-9a-fA-F]{1,6}) ?/', $selector, $hex, 0, $i) === 1) {
                        $name .= mb_chr((int) hexdec($hex[1]));
                        $i += strlen($hex[0]) - 1;
                    } elseif ($d === '\\' && $i + 1 < $n) {
                        $name .= $selector[++$i];
                    } elseif (preg_match('/[A-Za-z0-9_-]/', $d) === 1 || ord($d) >= 0x80) {
                        $name .= $d;
                    } else {
                        break;
                    }
                }

                $i--;
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * What the site's chrome (the layout, its header and footer) and the public JS can show, by this build: the classes
     * the two sheets' selectors need (`styled`), and of them those the chrome and the JS name (`words`), a word or a prefix
     * a template or a joined string builds a class from ("s-tone-" before ${...}).
     *
     * @return array{styled: array<string, true>, words: array<string, true>, hash: string}
     */
    public static function known(string $webPath): array
    {
        $sheets = self::$sheets[$webPath] ??= StaticVersions::of($webPath, self::WHOLE).StaticVersions::of($webPath, self::GAME).':'.filemtime(__FILE__);
        // A deployed build's files never change (APP_BUILD, its commit): read once per build and sheets. Locally, again
        // when a view or a script changes (their newest time: a few milliseconds of stat, never a read). In this
        // container's own file cache: a few hundred KB that no other server needs, kept out of the database.
        $build = config('app.build');
        $key = 'site-css:known:'.(is_string($build) && $build !== '' ? $build : 'local:'.self::newest($webPath)).':'.$sheets;

        return self::$known[$key] ??= Cache::store('file')->rememberForever($key, static fn (): array => self::read($webPath));
    }

    /** Locally: the newest change among the views and the scripts, and how many there are (one gone counts too); once a request. */
    private static function newest(string $webPath): string
    {
        return self::$newest[$webPath] ??= self::stat($webPath);
    }

    private static function stat(string $webPath): string
    {
        $newest = 0;
        $count = 0;

        foreach ([resource_path('views'), rtrim($webPath, '/').'/js'] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file instanceof \SplFileInfo) {
                    $newest = max($newest, $file->getMTime());
                    $count++;
                }
            }
        }

        return $newest.'.'.$count;
    }

    /**
     * known()'s reading.
     *
     * @return array{styled: array<string, true>, words: array<string, true>, hash: string}
     */
    private static function read(string $webPath): array
    {
        $styled = [];

        foreach ([self::WHOLE, self::GAME] as $sheet) {
            $path = rtrim($webPath, '/').$sheet;
            // Every class a selector needs: cut() asks of each one, and keeps nothing when the answer is always no.
            self::cut((string) preg_replace('#/\*.*?\*/#s', '', is_file($path) ? (string) file_get_contents($path) : ''), static function (string $class) use (&$styled): bool {
                $styled[$class] = true;

                return false;
            });
        }

        $words = [];
        $prefixes = [];

        foreach (self::sources($webPath) as $file) {
            $code = (string) file_get_contents($file);
            // A class in a template or a string is a run between spaces and quotes (an arbitrary value keeps its
            // parentheses and commas: text-[clamp(5rem,30vw,9rem)]); the stricter words split it at what a class can't
            // hold, for one joined to code ("classList.add").
            preg_match_all('/[^\s\'"`]+/', $code, $loose);
            preg_match_all('/[A-Za-z_][A-Za-z0-9_-]*/', $code, $strict);
            preg_match_all('/(?<![A-Za-z0-9_-])([A-Za-z][A-Za-z0-9_-]*-)(?:\$\{|\{\{|[\'"`]\s*[+.])/', $code, $pre);

            foreach ([...$loose[0], ...$strict[0]] as $word) {
                $words[$word] = true;
            }

            foreach ($pre[1] as $prefix) {
                $prefixes[] = $prefix;
            }
        }

        $known = [];

        foreach (array_keys($styled) as $class) {
            $class = (string) $class;

            if (isset($words[$class]) || array_filter($prefixes, static fn (string $prefix): bool => str_starts_with($class, $prefix)) !== []) {
                $known[$class] = true;
            }
        }

        ksort($known);

        return ['styled' => $styled, 'words' => $known, 'hash' => hash('xxh128', implode(' ', array_keys($known)))];
    }

    /**
     * The site's chrome and every JS file the public pages can load: each /js/ module a server view names, all of
     * js/public and js/embed, and everything they import, followed through (tool/app_css.py's public_js).
     *
     * @return list<string>
     */
    public static function sources(string $webPath): array
    {
        $web = rtrim($webPath, '/');
        $views = resource_path('views');
        $seeds = [];

        foreach (self::files($views, '.php') as $view) {
            preg_match_all('#/js/([A-Za-z0-9_./-]+\.js)#', (string) file_get_contents($view), $m);

            foreach ($m[1] as $module) {
                $seeds[] = $web.'/js/'.$module;
            }
        }

        foreach (['/js/public', '/js/embed'] as $dir) {
            $seeds = [...$seeds, ...self::files($web.$dir, '.js')];
        }

        $seen = [];
        $todo = array_values(array_filter(array_map(static fn (string $p): string|false => realpath($p), $seeds), 'is_string'));
        $root = (string) realpath($web);

        while ($todo !== []) {
            $path = (string) array_pop($todo);

            if (isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;
            preg_match_all('/(?:\bimport\s*(?:[^\'"`;()]*?\bfrom\s*)?|\bimport\(\s*|\bexport\s+[^\'"`;]*?\bfrom\s*)[\'"]([^\'"]+\.js)[\'"]/', (string) file_get_contents($path), $m);

            foreach ($m[1] as $spec) {
                $target = realpath(str_starts_with($spec, '/') ? $web.$spec : dirname($path).'/'.$spec);

                if (is_string($target) && is_file($target) && str_starts_with($target, $root.'/')) {
                    $todo[] = $target;
                }
            }
        }

        // The site's chrome, on every page whatever it shows now and then (the language switcher, an arrival's line): the
        // layout, the header and the footer, and each <x-site.*> component they use, followed through.
        $chrome = [];
        $next = [$views.'/public/layout.blade.php', $views.'/components/site/header.blade.php', $views.'/components/site/footer.blade.php'];

        while ($next !== []) {
            $view = (string) array_pop($next);

            if (isset($chrome[$view]) || ! is_file($view)) {
                continue;
            }

            $chrome[$view] = true;
            preg_match_all('/<x-site\.([a-z0-9-]+)/', (string) file_get_contents($view), $m);

            foreach ($m[1] as $component) {
                $next[] = $views.'/components/site/'.$component.'.blade.php';
            }
        }

        $files = [...array_keys($seen), ...array_keys($chrome)];
        sort($files);

        return $files;
    }

    /**
     * A sheet (or a block's inside) less the rules none of whose selectors `$can` match, comments already gone.
     *
     * @param  \Closure(string): bool  $can
     */
    public static function cut(string $css, \Closure $can): string
    {
        $out = '';

        foreach (self::statements($css) as $statement) {
            $brace = strpos($statement, '{');

            if ($brace === false) {
                $out .= $statement;

                continue;
            }

            $head = trim(substr($statement, 0, $brace));

            if (str_starts_with($head, '@')) {
                if (array_filter(self::BLOCKS, static fn (string $block): bool => str_starts_with($head, $block)) !== []) {
                    $inner = self::cut(substr($statement, $brace + 1, -1), $can);

                    // An empty @layer still says where its layer goes; an empty @media says nothing.
                    if ($inner !== '' || str_starts_with($head, '@layer')) {
                        $out .= substr($statement, 0, $brace + 1).$inner.'}';
                    }
                } else {
                    $out .= $statement;
                }

                continue;
            }

            foreach (self::selectors($head) as $selector) {
                if (array_filter(self::required($selector), static fn (string $class): bool => ! $can($class)) === []) {
                    $out .= $statement;

                    break;
                }
            }
        }

        return $out;
    }

    /** The index of the bracket that closes the one at $at, quotes and escapes skipped. */
    private static function closing(string $s, int $at, string $open, string $close): int
    {
        $depth = 0;
        $n = strlen($s);

        for ($i = $at; $i < $n; $i++) {
            $c = $s[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '"' || $c === "'") {
                for ($i++; $i < $n && $s[$i] !== $c; $i++) {
                    if ($s[$i] === '\\') {
                        $i++;
                    }
                }
            } elseif ($c === $open) {
                $depth++;
            } elseif ($c === $close && --$depth === 0) {
                return $i;
            }
        }

        return $n;
    }

    /**
     * A selector list split at its top-level commas (escapes, brackets, parentheses and quotes respected).
     *
     * @return list<string>
     */
    private static function selectors(string $head): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $n = strlen($head);

        for ($i = 0; $i < $n; $i++) {
            $c = $head[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '"' || $c === "'") {
                for ($i++; $i < $n && $head[$i] !== $c; $i++) {
                    if ($head[$i] === '\\') {
                        $i++;
                    }
                }
            } elseif ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = trim(substr($head, $start, $i - $start));
                $start = $i + 1;
            }
        }

        $parts[] = trim(substr($head, $start));

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    /**
     * The top-level statements of a sheet (a rule, a block or a ";" statement); comments already gone.
     *
     * @return list<string>
     */
    private static function statements(string $css): array
    {
        $out = [];
        $depth = 0;
        $start = 0;
        $n = strlen($css);

        for ($i = 0; $i < $n; $i++) {
            $c = $css[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '"' || $c === "'") {
                for ($i++; $i < $n && $css[$i] !== $c; $i++) {
                    if ($css[$i] === '\\') {
                        $i++;
                    }
                }
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                if (--$depth === 0) {
                    $out[] = trim(substr($css, $start, $i + 1 - $start));
                    $start = $i + 1;
                }
            } elseif ($c === ';' && $depth === 0) {
                $out[] = trim(substr($css, $start, $i + 1 - $start));
                $start = $i + 1;
            }
        }

        return array_values(array_filter($out, static fn (string $s): bool => $s !== ''));
    }

    /**
     * The files under a directory with that ending.
     *
     * @return list<string>
     */
    private static function files(string $dir, string $ending): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), $ending)) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
