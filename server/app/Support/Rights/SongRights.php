<?php

declare(strict_types=1);

namespace App\Support\Rights;

/**
 * Whether a song may be sung in a country (docs/geo-rights-plan.md; Hillel, 2026-11-12: "please track this info in the
 * app and automatically apply each country's laws", and "let's be a good citizen"): from its makers (who wrote the music,
 * the words, the translation we sing, an arrangement we use, and when each died) and its first printing, by that
 * country's copyright term (config/copyright_terms.php, each with its source, for the lawyer). Withhold rather than
 * guess: a maker whose death is unknown, a living one, or a missing first printing keeps a song protected, and an
 * unknown country (no country, Cloudflare's XX and T1) gets every country's rule at once.
 */
final class SongRights
{
    /** The makers whose rights cover a song's words (the page's lyric lines, its full words, its meanings). */
    public const WORDS = ['words', 'translation'];

    /** Countries that stand for "we don't know where": the strictest of every rule applies. */
    public const UNKNOWN = ['XX', 'T1', ''];

    /**
     * A song's status in a country: free, or protected until the year it becomes free (null when that can't be known:
     * a maker living or of unknown death, or no first printing).
     *
     * With `$roles`, only those makers count (the words alone: self::WORDS); a song none of whose makers has one of them
     * is, for those roles, traditional, and goes by its first printing.
     *
     * @param  array<string, mixed>  $song  its file's data (makers, firstPublished, made)
     * @param  list<string>|null  $roles
     * @return array{free: bool, until: int|null, reason: string}
     */
    public static function status(array $song, ?string $country, ?int $year = null, ?array $roles = null): array
    {
        $year ??= (int) now()->year;
        if ($roles !== null) {
            $song['makers'] = array_values(array_filter((array) ($song['makers'] ?? []), static fn (mixed $maker): bool => is_array($maker) && in_array($maker['role'] ?? null, $roles, true)));
        }
        $country = strtoupper((string) $country);

        if (in_array($country, self::UNKNOWN, true)) {
            return self::strictest($song, $year);
        }
        $rule = self::rule($country);
        if ($rule === null) {
            // A real country with no row is the table's gap, never a guess: the strictest rule, and a test fails it.
            return [...self::strictest($song, $year), 'reason' => 'no-rule'];
        }
        if (($rule['confidence'] ?? 'high') === 'low') {
            // A law we couldn't read for certain (Somalia's, the Marshall Islands'): the strictest rule until it's checked.
            return [...self::strictest($song, $year), 'reason' => 'low-confidence'];
        }

        $freeFrom = self::freeFrom($song, $rule);
        if ($freeFrom !== null && ($rule['shorterTerm'] ?? false) === true) {
            $origin = strtoupper((string) ($song['firstPublished']['country'] ?? ''));
            $theirs = $origin !== '' && $origin !== $country && self::rule($origin) !== null ? self::freeFrom($song, (array) self::rule($origin)) : null;
            if ($theirs !== null && $theirs < $freeFrom) {
                $freeFrom = $theirs;
            }
        }

        return $freeFrom === null
            ? ['free' => false, 'until' => null, 'reason' => 'unknown']
            : ['free' => $freeFrom <= $year, 'until' => $freeFrom <= $year ? null : $freeFrom, 'reason' => 'term'];
    }

    /**
     * The year a song becomes free under a rule (1 January of it), or null when that can't be known.
     *
     * @param  array<string, mixed>  $song
     * @param  array<string, mixed>  $rule
     */
    public static function freeFrom(array $song, array $rule): ?int
    {
        $published = $song['firstPublished'] ?? null;
        $printed = is_array($published) && is_int($published['year'] ?? null) ? $published['year'] : null;
        // The year it was written, for a song never printed (a manuscript): only when there's no first printing.
        $made = $printed === null && is_int($song['made'] ?? null) ? $song['made'] : null;
        $makers = array_values(array_filter((array) ($song['makers'] ?? []), 'is_array'));

        // The US: by publication (before 1931, free; later, 95 years after; circular 15a).
        if (is_array($rule['publicationBased'] ?? null)) {
            $by = $rule['publicationBased'];
            if ($printed === null) {
                // Never published before 1978: the author's life and 70 years, or 120 from its making when anonymous.
                if ($made === null) {
                    return null;
                }
                $died = self::died($makers);

                return $makers === [] ? $made + (int) ($by['anonymousFromMaking'] ?? 120) + 1 : ($died === null ? null : $died + (int) ($rule['life'] ?? 0) + 1);
            }
            if ($printed < (int) $by['freeBefore']) {
                return 0;
            }
            // A US work printed before 1978 without a notice fell into the public domain, and one printed before 1964 whose
            // copyright wasn't renewed ended after its first 28 years. Only when known: "unknown" or missing keeps the 95.
            // A foreign work's US copyright may have been restored (1996), so the relief is the US's own works'.
            if (strtoupper((string) ($published['country'] ?? '')) === 'US') {
                if ($printed < 1978 && ($published['notice'] ?? null) === false) {
                    return 0;
                }
                if ($printed < 1964 && ($published['renewed'] ?? null) === false) {
                    return $printed + 28 + 1;
                }
            }

            return $printed + (int) $by['term'] + 1;
        }

        if ($makers === []) {
            // Traditional or anonymous: by its first printing (or, never printed, its making), as long as the longest life
            // term after it, to be safe.
            $from = $printed ?? $made;

            return $from === null ? null : $from + (int) ($rule['life'] ?? 0) + 1;
        }
        $died = self::died($makers);
        if ($died === null) {
            return null;
        }

        if (is_int($rule['freeIfDiedBefore'] ?? null) && $died < $rule['freeIfDiedBefore']) {
            return 0;
        }
        $life = (int) ($rule['life'] ?? 0);
        foreach ((array) ($rule['exceptions'] ?? []) as $exception) {
            if (! is_array($exception) || ! is_int($exception['life'] ?? null)) {
                continue; // e.g. India's term from a posthumous publication: our songs were printed in their makers' lives
            }
            // Each exception's `life` is its whole term. A death known only by its year counts as before a date in that
            // year, an unknown printing as before any date, and a condition we can't know (an author "mort pour la
            // France", a war veteran) as met: the longer term. But only where it could be true at all: `diedIn` lists
            // the years a death must fall in (a war's, for France's; alive in the war, for Russia's), so an American
            // hymn writer who died at home in 1921 never takes France's +30 (the arranger's check, 2026-11-13).
            $applies = match (true) {
                is_string($exception['diedBefore'] ?? null) => $died <= (int) substr($exception['diedBefore'], 0, 4),
                is_string($exception['publishedBefore'] ?? null) => $printed === null || $printed <= (int) substr($exception['publishedBefore'], 0, 4),
                default => true,
            };
            if ($applies && is_array($exception['diedIn'] ?? null)) {
                $applies = array_filter($exception['diedIn'], static fn (mixed $years): bool => is_array($years) && $died >= (int) ($years[0] ?? PHP_INT_MAX) && $died <= (int) ($years[1] ?? PHP_INT_MIN)) !== [];
            }
            if ($applies && is_int($exception['freeIfDiedBefore'] ?? null) && $died < $exception['freeIfDiedBefore']) {
                $applies = false;
            }
            if ($applies) {
                $life = max($life, $exception['life']);
            }
        }

        return $died + $life + 1;
    }

    /**
     * Free only where it's free everywhere: the latest year of every country's rule.
     *
     * @param  array<string, mixed>  $song
     * @return array{free: bool, until: int|null, reason: string}
     */
    private static function strictest(array $song, int $year): array
    {
        $latest = 0;
        foreach (array_keys((array) config('copyright_terms.countries')) as $code) {
            $freeFrom = self::freeFrom($song, (array) self::rule((string) $code));
            if ($freeFrom === null) {
                return ['free' => false, 'until' => null, 'reason' => 'unknown'];
            }
            $latest = max($latest, $freeFrom);
        }

        return ['free' => $latest <= $year, 'until' => $latest <= $year ? null : $latest, 'reason' => 'strictest'];
    }

    /**
     * The last of the makers' deaths, or null when one is living or unknown.
     *
     * @param  list<array<string, mixed>>  $makers
     */
    private static function died(array $makers): ?int
    {
        $deaths = [];
        foreach ($makers as $maker) {
            if (! is_int($maker['died'] ?? null)) {
                return null;
            }
            $deaths[] = $maker['died'];
        }

        return $deaths === [] ? null : max($deaths);
    }

    /** @return array<string, mixed>|null */
    private static function rule(string $country): ?array
    {
        $parent = config('copyright_terms.inherits.'.$country);
        $rule = config('copyright_terms.countries.'.(is_string($parent) ? $parent : $country));

        return is_array($rule) ? $rule : null;
    }
}
