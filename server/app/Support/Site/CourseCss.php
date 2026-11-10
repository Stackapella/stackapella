<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Http\StaticVersions;
use Throwable;

/**
 * The course pages' stylesheet, only the courses a page shows (docs/css-split-plan.md, step 1). css/courses.css holds every
 * course page's .cp-* rules, about 320 KB, and a page shows one or two courses: the layout links
 * /course-css/<prefixes>.css?v=<courses.css's hash> (Public\CourseCssController), whose sheet is courses.css with only the
 * rules of the courses the page shows, and every rule that isn't one course's, in courses.css's own order. Nothing that
 * could match the page is left out and nothing moves, so the page looks exactly as with the whole file: separate files per
 * course can't promise that, as an element can carry the shared demo kit's class and its course's, both setting the same
 * thing, and today the later rule in courses.css wins.
 *
 * A rule belongs to a course when every selector in its list needs that course's class (.cp-bb2-hero is bb2's), what only
 * :not(), :is() or :where() name never counting; one that needs two courses at once (".cp-dk .cp-sc-x") is the later
 * one's, kept whenever that one shows; anything else (no course class, a list spanning courses, or a course with under
 * SMALL bytes of rules of its own) is kept on every page.
 */
final class CourseCss
{
    public const WHOLE = '/css/courses.css';

    /** The key of a page that shows course classes but no course with rules of its own. */
    public const BASE = 'base';

    private const PREFIX = '/\.cp-([a-z]+[0-9]?)(?![a-z0-9])/';

    private const BLOCKS = ['@media', '@supports', '@container', '@layer'];

    /** A course with fewer bytes of rules than this is kept on every page (see parse()). */
    private const SMALL = 2048;

    /** @var array<string, array{prefixes: list<string>, rules: list<array{0: string|null, 1: list<string>, 2: string}>}> */
    private static array $parsed = [];

    /**
     * The stylesheet link for a page's rendered HTML, or null when it shows no course. If anything fails, the whole
     * css/courses.css, as before the split.
     */
    public static function url(string $webPath, string $html): ?string
    {
        $shown = self::shown($html);

        if ($shown === []) {
            return null;
        }

        try {
            $version = self::version($webPath);

            if ($version === '') {
                return self::WHOLE;
            }

            return '/course-css/'.self::key(array_values(array_intersect($shown, self::known($webPath)))).'.css?v='.$version;
        } catch (Throwable $error) {
            report($error);

            return StaticVersions::url($webPath, self::WHOLE);
        }
    }

    /**
     * The course prefixes a page's HTML shows in its classes ("cp-bb2-hero" is bb2).
     *
     * @return list<string>
     */
    public static function shown(string $html): array
    {
        if (preg_match_all('/class="([^"]*)"/', $html, $classes) === 0) {
            return [];
        }

        preg_match_all('/(?<![\w-])cp-([a-z]+[0-9]?)(?![a-z0-9])/', implode(' ', $classes[1]), $found);

        return array_values(array_unique($found[1]));
    }

    /**
     * The canonical key for a set of prefixes: sorted, each once, joined by "."; BASE for none.
     *
     * @param  list<string>  $prefixes
     */
    public static function key(array $prefixes): string
    {
        $prefixes = array_values(array_unique($prefixes));
        sort($prefixes);

        return $prefixes === [] ? self::BASE : implode('.', $prefixes);
    }

    /**
     * The courses with rules of their own in css/courses.css (the only prefixes a key may name).
     *
     * @return list<string>
     */
    public static function known(string $webPath): array
    {
        return self::parsed($webPath)['prefixes'];
    }

    /** css/courses.css's version: its hash, the layout's ?v= ('' when it's missing). */
    public static function version(string $webPath): string
    {
        return StaticVersions::of($webPath, self::WHOLE);
    }

    /**
     * The sheet for those courses: courses.css's rules that are theirs or no one course's, in its order, each in its
     *
     * @media or @supports.
     *
     * @param  list<string>  $prefixes
     */
    public static function sheet(string $webPath, array $prefixes): string
    {
        return self::assemble(self::parsed($webPath)['rules'], $prefixes);
    }

    /**
     * courses.css's rules, each with its course (null for a rule every page keeps), and the courses with rules.
     *
     * @return array{prefixes: list<string>, rules: list<array{0: string|null, 1: list<string>, 2: string}>}
     */
    public static function parse(string $css): array
    {
        preg_match_all(self::PREFIX, $css, $all);
        $rank = array_flip(array_values(array_unique($all[1])));
        $rules = [];
        $size = [];

        foreach (self::rules($css, []) as [$wrap, $code]) {
            $course = self::course($code, $rank);
            $rules[] = [$course, $wrap, $code];

            if ($course !== null) {
                $size[$course] = ($size[$course] ?? 0) + strlen($code);
            }
        }

        // A small group (a shared piece such as .cp-faq, .cp-related) is kept on every page: a few hundred bytes aren't
        // worth a key, and it keeps the keys few (a page names its one or two courses and the demo kit, not twenty).
        $own = array_filter($size, static fn (int $bytes): bool => $bytes >= self::SMALL);
        $rules = array_map(static fn (array $rule): array => [$rule[0] !== null && isset($own[$rule[0]]) ? $rule[0] : null, $rule[1], $rule[2]], $rules);
        $prefixes = array_map(static fn (int|string $prefix): string => (string) $prefix, array_keys($own));
        sort($prefixes);

        return ['prefixes' => $prefixes, 'rules' => $rules];
    }

    /**
     * Parsed rules put back together: those of the given courses and of none, in order, each in its wrappers.
     *
     * @param  list<array{0: string|null, 1: list<string>, 2: string}>  $rules
     * @param  list<string>  $prefixes
     */
    public static function assemble(array $rules, array $prefixes): string
    {
        $wanted = array_flip($prefixes);
        $text = '';
        $open = [];

        foreach ($rules as [$course, $wrap, $code]) {
            if ($course !== null && ! isset($wanted[$course])) {
                continue;
            }

            if ($wrap !== $open) {
                $text .= str_repeat('}', count($open)).implode('', $wrap);
                $open = $wrap;
            }

            $text .= $code;
        }

        return $text.str_repeat('}', count($open));
    }

    /** @return array{prefixes: list<string>, rules: list<array{0: string|null, 1: list<string>, 2: string}>} */
    private static function parsed(string $webPath): array
    {
        $path = rtrim($webPath, '/').self::WHOLE;
        $key = $path.'@'.self::version($webPath);

        return self::$parsed[$key] ??= self::parse(is_file($path) ? (string) file_get_contents($path) : '');
    }

    /**
     * Every rule of a built sheet as [the @media/@supports heads it sits in, outermost first; the rule].
     *
     * @param  list<string>  $wrap
     * @return list<array{0: list<string>, 1: string}>
     */
    private static function rules(string $css, array $wrap): array
    {
        $out = [];

        foreach (self::statements($css) as $statement) {
            $brace = strpos($statement, '{');

            if ($brace === false) {
                $out[] = [$wrap, $statement];

                continue;
            }

            $head = trim(substr($statement, 0, $brace));

            if (array_filter(self::BLOCKS, static fn (string $block): bool => str_starts_with($head, $block)) !== []) {
                $out = [...$out, ...self::rules(substr($statement, $brace + 1, -1), [...$wrap, substr($statement, 0, $brace + 1)])];
            } else {
                $out[] = [$wrap, $statement];
            }
        }

        return $out;
    }

    /**
     * The top-level statements of a sheet (a rule, a block or a ";" statement), its comments left out.
     *
     * @return list<string>
     */
    private static function statements(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $out = [];
        $depth = 0;
        $start = 0;
        $n = strlen($css);

        for ($i = 0; $i < $n; $i++) {
            $c = $css[$i];

            if ($c === '"' || $c === "'") {
                for ($i++; $i < $n && $css[$i] !== $c; $i++) {
                    if ($css[$i] === '\\') {
                        $i++;
                    }
                }

                continue;
            }

            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;

                if ($depth === 0) {
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
     * The course a rule belongs to, or null when every page keeps it.
     *
     * @param  array<string, int>  $rank
     */
    private static function course(string $code, array $rank): ?string
    {
        $brace = strpos($code, '{');

        if ($brace === false) {
            return null;
        }

        $head = trim(substr($code, 0, $brace));

        if (str_starts_with($head, '@keyframes')) {
            return preg_match('/^@keyframes\s+cp-([a-z]+[0-9]?)(?![a-z0-9])/', $head, $m) === 1 && isset($rank[$m[1]]) ? $m[1] : null;
        }

        if (str_starts_with($head, '@')) {
            return null;
        }

        $courses = [];

        foreach (self::selectors($head) as $selector) {
            preg_match_all(self::PREFIX, self::required($selector), $m);
            $needed = array_values(array_unique($m[1]));

            if ($needed === []) {
                return null;
            }

            usort($needed, static fn (string $a, string $b): int => ($rank[$a] ?? 0) <=> ($rank[$b] ?? 0));
            $courses[(string) end($needed)] = true;
        }

        return count($courses) === 1 ? (string) array_key_first($courses) : null;
    }

    /**
     * A selector list split at its top-level commas.
     *
     * @return list<string>
     */
    private static function selectors(string $head): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($head) as $c) {
            if ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth--;
            }

            if ($c === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $c;
            }
        }

        $parts[] = trim($current);

        return array_values(array_filter($parts, static fn (string $s): bool => $s !== ''));
    }

    /** The selector with what needn't match taken out: :not(), :is() and :where() with what's in them. */
    private static function required(string $selector): string
    {
        return (string) preg_replace('/:(?:not|is|where)(\((?:[^()]|(?1))*\))/', '', $selector);
    }
}
