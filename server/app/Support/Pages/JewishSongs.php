<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Carbon;

/**
 * The Jewish songs page (/songs/jewish-songs; Hillel, 2026-11-11: "a 'traditional jewish songs' page like we have a carols
 * page"), a song collection with two things of its own: its countdown to the next festival (config site.festivals,
 * Hebcal's dates) and each song's festival as a tag on its card, from its file's genre. Its season on /songs is a few
 * weeks before Hanukkah and Passover until they end. Words: site.collections.jewish-songs.
 */
final class JewishSongs
{
    public const SLUG = 'jewish-songs';

    /** The festivals a song's genre can name, in the order a tag prefers (a Hanukkah song that's also sung every Shabbat is a Hanukkah song). */
    public const FESTIVALS = ['hanukkah', 'passover', 'purim', 'shabbat'];

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

    /**
     * The hero's countdown card for `festival` (from festival()): the shared festival card (site._festival-card) in the
     * page's own words, with that festival's songs to learn in time.
     *
     * @param  array{key: string, from: Carbon, until: Carbon, days: int, on: bool}  $festival
     * @param  list<array<string, mixed>>  $songs
     * @return array<string, mixed>|null
     */
    public static function card(array $festival, array $songs): ?array
    {
        foreach (Festivals::all() as $f) {
            if ($f['collection'] === self::SLUG && $f['key'] === $festival['key'] && $f['from']->equalTo($festival['from'])) {
                $w = 'site.collections.'.self::SLUG;
                $learn = trans()->has($w.'.count_learn.'.$f['key']) ? (string) __($w.'.count_learn.'.$f['key']) : null;

                return Festivals::card($f, (string) __($w.'.festivals.'.$f['key']), [
                    'kick' => $w.'.count_label', 'kick_on' => $w.'.count_on_label', 'days' => $w.'.count_days', 'tonight' => $w.'.count_tonight',
                    'today' => $w.'.count_tonight', 'start' => $w.'.count_sundown', 'start_evening' => $w.'.count_sundown', 'until' => $w.'.count_until',
                ], $learn !== null ? $songs : [], $learn);
            }
        }

        return null;
    }

    /**
     * The festival whose search title the page wears now, or null: one of its own festivals whose window is open
     * (Festivals::state: from `ahead` days before it to its last day, so Hanukkah's from 13 November to 12 December
     * 2026) and that has a title of its own (site.collections.jewish-songs.season_title). The coordinator, 2026-11-13:
     * people search the festival by name before the day, as the carols page leads with Christmas in its season.
     */
    public static function seasonKey(?Carbon $today = null): ?string
    {
        foreach (Festivals::all() as $festival) {
            if ($festival['collection'] === self::SLUG && Festivals::state($festival, $today ?? now())['open'] && trans()->has('site.collections.'.self::SLUG.'.season_title.'.$festival['key'])) {
                return $festival['key'];
            }
        }

        return null;
    }

    /** Whether /songs leads with the page: while a festival of its own in the calendar (Hanukkah, Passover) is open. */
    public static function inSeason(): bool
    {
        foreach (Festivals::all() as $festival) {
            if ($festival['collection'] === self::SLUG && Festivals::state($festival, now())['open']) {
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
     * Each first evening of a festival in the config, as Y-m-d, earliest first (the lamp's script counts Hanukkah's night
     * from these, in the visitor's own day: Hanukkiah).
     *
     * @return list<string>
     */
    public static function firsts(string $key): array
    {
        return array_values(array_map(static fn (array $f): string => $f['from']->format('Y-m-d'), array_filter(self::dates(), static fn (array $f): bool => $f['key'] === $key)));
    }

    /**
     * The page's festivals in the calendar (config site.festivals, App\Support\Pages\Festivals), in date order, each with
     * its first day and last.
     *
     * @return list<array{key: string, from: Carbon, until: Carbon}>
     */
    private static function dates(): array
    {
        return array_values(array_map(
            static fn (array $f): array => ['key' => $f['key'], 'from' => $f['from'], 'until' => $f['until']],
            array_filter(Festivals::all(), static fn (array $f): bool => $f['collection'] === self::SLUG),
        ));
    }
}
