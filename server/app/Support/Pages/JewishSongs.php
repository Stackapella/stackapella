<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Carbon;

/**
 * The Jewish songs page (/songs/jewish-songs; Hillel, 2026-11-11: "a 'traditional jewish songs' page like we have a carols
 * page"), a song collection with two things of its own: its countdown to the next festival (config site.jewish_festivals,
 * Hebcal's dates) and each song's festival as a tag on its card, from its file's genre. Its season on /songs is a few
 * weeks before Hanukkah and Passover until they end. Words: site.collections.jewish-songs.
 */
final class JewishSongs
{
    public const SLUG = 'jewish-songs';

    /** The festivals a song's genre can name, in the order a tag prefers (a Hanukkah song that's also sung every Shabbat is a Hanukkah song). */
    public const FESTIVALS = ['hanukkah', 'passover', 'purim', 'shabbat'];

    /** The festivals that bring the page into season on /songs, and how many days ahead. */
    public const SEASONS = ['hanukkah', 'passover'];

    public const AHEAD = 21;

    /**
     * The festival on now or next: its key, its first evening and last day, the days until it begins (0 on its first
     * evening and while it's on) and whether it's on. Null with none ahead in the config.
     *
     * @return array{key: string, from: Carbon, until: Carbon, days: int, on: bool}|null
     */
    public static function festival(): ?array
    {
        $today = now()->startOfDay();
        foreach (self::dates() as $festival) {
            if ($today->lessThanOrEqualTo($festival['until'])) {
                $on = $today->greaterThanOrEqualTo($festival['from']);

                return [...$festival, 'days' => $on ? 0 : (int) $today->diffInDays($festival['from']), 'on' => $on];
            }
        }

        return null;
    }

    /** Whether /songs leads with the page: from AHEAD days before Hanukkah or Passover until it ends. */
    public static function inSeason(): bool
    {
        $today = now()->startOfDay();
        foreach (self::dates() as $festival) {
            if (in_array($festival['key'], self::SEASONS, true) && $today->greaterThanOrEqualTo($festival['from']->copy()->subDays(self::AHEAD)) && $today->lessThanOrEqualTo($festival['until'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * A song's festival tag from its genres: the first of FESTIVALS it names, else every day.
     *
     * @param  list<string>  $genres
     */
    public static function tag(array $genres): string
    {
        $own = array_map(static fn (string $g): string => strtolower(trim($g)), $genres);
        foreach (self::FESTIVALS as $festival) {
            if (in_array($festival, $own, true)) {
                return $festival;
            }
        }

        return 'every-day';
    }

    /**
     * The configured festivals, in date order, each with its dates as days.
     *
     * @return list<array{key: string, from: Carbon, until: Carbon}>
     */
    private static function dates(): array
    {
        $dates = [];
        foreach ((array) config('site.jewish_festivals') as $festival) {
            if (is_array($festival) && is_string($festival['key'] ?? null) && is_string($festival['from'] ?? null) && is_string($festival['until'] ?? null)) {
                $dates[] = ['key' => $festival['key'], 'from' => Carbon::parse($festival['from'])->startOfDay(), 'until' => Carbon::parse($festival['until'])->startOfDay()];
            }
        }
        usort($dates, static fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        return $dates;
    }
}
