<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Support\Languages\Dates;
use Illuminate\Support\Carbon;

/**
 * The festivals calendar (config site.festivals; Hillel, 2026-11-12: "have the main songs page automatically highlight
 * which section is coming up next"): every date-aware collection's festivals, from one list. A festival's window opens
 * `ahead` days before its first day and closes after its last; while it's open and its collection page is live, /songs
 * leads with it as "Coming up" (the nearest, or the one on now, as a card; a second as a smaller line). The page is cached,
 * so js/public/coming-up.js picks again from the visitor's own date with the same rules (`state`); the server's pick for
 * its own day is the page without the script. The Jewish songs page's countdown reads the same list (JewishSongs).
 */
final class Festivals
{
    /** How many days before its first day a festival leads /songs, unless it says otherwise. */
    public const AHEAD = 35;

    /** The candidates /songs renders for its script: open now, or opening within this many days (the page's cache is far shorter). */
    public const LOOKAHEAD = 60;

    /**
     * Every festival in the calendar, earliest first: its key, collection, first day, last day (the day it ends; for one
     * that begins at sundown, `days` days after its first evening), whether it begins at sundown, and its `ahead`.
     *
     * @return list<array{key: string, collection: string, from: Carbon, until: Carbon, evening: bool, days: int, ahead: int}>
     */
    public static function all(): array
    {
        $out = [];
        foreach ((array) config('site.festivals') as $f) {
            if (! is_array($f) || ! is_string($f['key'] ?? null) || ! is_string($f['collection'] ?? null) || ! is_string($f['from'] ?? null)) {
                continue;
            }
            $from = Carbon::parse($f['from'])->startOfDay();
            $evening = (bool) ($f['evening'] ?? false);
            $days = max(1, (int) ($f['days'] ?? 1));
            $out[] = [
                'key' => $f['key'],
                'collection' => $f['collection'],
                'from' => $from,
                'until' => $from->copy()->addDays($evening ? $days : $days - 1),
                'evening' => $evening,
                'days' => $days,
                'ahead' => max(0, (int) ($f['ahead'] ?? self::AHEAD)),
            ];
        }
        usort($out, static fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        return $out;
    }

    /**
     * A festival on `today` (a date): how many days until its first day (0 on it, negative after), whether it's on (from
     * its first day, or the day after its first evening, through its last day), and whether its window is open.
     *
     * @param  array{from: Carbon, until: Carbon, evening: bool, ahead: int}  $f
     * @return array{days: int, on: bool, open: bool}
     */
    public static function state(array $f, Carbon $today): array
    {
        $day = $today->copy()->startOfDay();
        $days = (int) round($day->diffInDays($f['from'], false));
        $ended = $day->greaterThan($f['until']);

        return [
            'days' => $days,
            'on' => ! $ended && ($days < 0 || ($days === 0 && ! $f['evening'])),
            'open' => $f['ahead'] > 0 && ! $ended && $days <= $f['ahead'],
        ];
    }

    /**
     * The festivals leading /songs on `today`, the one to show as the card first: those on now, then the nearest. Only
     * those whose collection is in `live`.
     *
     * @param  list<string>  $live
     * @return list<array{key: string, collection: string, from: Carbon, until: Carbon, evening: bool, days: int, ahead: int}>
     */
    public static function open(array $live, ?Carbon $today = null): array
    {
        $today ??= now();
        $open = array_values(array_filter(self::all(), static fn (array $f): bool => in_array($f['collection'], $live, true) && self::state($f, $today)['open']));
        usort($open, static fn (array $a, array $b): int => [! self::state($a, $today)['on'], $a['from']] <=> [! self::state($b, $today)['on'], $b['from']]);

        return $open;
    }

    /**
     * The festivals /songs renders for its script to pick from: live, not ended, and open or opening within LOOKAHEAD days.
     *
     * @param  list<string>  $live
     * @return list<array{key: string, collection: string, from: Carbon, until: Carbon, evening: bool, days: int, ahead: int}>
     */
    public static function candidates(array $live, ?Carbon $today = null): array
    {
        $today ??= now();

        return array_values(array_filter(self::all(), static function (array $f) use ($live, $today): bool {
            $state = self::state($f, $today);

            return in_array($f['collection'], $live, true) && $f['ahead'] > 0 && $f['until']->greaterThanOrEqualTo($today->copy()->startOfDay()->subDay())
                && $state['days'] - $f['ahead'] <= self::LOOKAHEAD;
        }));
    }

    /**
     * A festival's card (site._festival-card): its name, what it says on `today` in `words` (the page's own: `kick`,
     * `kick_on`, `days` (a choice, "{1} Tomorrow|[2,*] :count days"), `tonight`, `today`, `start`, `start_evening`,
     * `until`), and the templates its script needs to say it again on the visitor's day.
     *
     * @param  array{key: string, collection: string, from: Carbon, until: Carbon, evening: bool, days: int, ahead: int}  $f
     * @param  array<string, string>  $words
     * @param  list<array<string, mixed>>  $songs
     * @param  array{url: string, label: string}|null  $link
     * @return array<string, mixed>
     */
    public static function card(array $f, string $name, array $words, array $songs = [], ?string $learn = null, ?array $link = null, ?Carbon $today = null): array
    {
        $state = self::state($f, $today ?? now());
        $when = match (true) {
            $state['on'] => '',
            $state['days'] === 0 => (string) __($f['evening'] ? $words['tonight'] : $words['today']),
            default => trans_choice($words['days'], $state['days'], ['count' => $state['days']]),
        };

        return [
            'key' => $f['key'],
            'band' => $f['collection'] === 'christmas-carols' ? 'christmas' : $f['collection'],
            'name' => $name,
            'from' => $f['from']->format('Y-m-d'),
            'until' => $f['until']->format('Y-m-d'),
            'evening' => $f['evening'],
            'ahead' => $f['ahead'],
            'on' => $state['on'],
            'kick' => (string) __($words['kick']),
            'kickOn' => (string) __($words['kick_on']),
            'when' => $when,
            'start' => (string) __($f['evening'] ? $words['start_evening'] : $words['start'], ['date' => Dates::weekdayDayMonth($f['from'])]),
            'untilLine' => (string) __($words['until'], ['date' => Dates::weekdayDayMonth($f['until'])]),
            'one' => trans_choice($words['days'], 1, ['count' => '#']),
            'other' => trans_choice($words['days'], 5, ['count' => '#']),
            'tonight' => (string) __($words['tonight']),
            'today' => (string) __($words['today']),
            'learn' => $songs !== [] ? $learn : null,
            'songs' => $songs,
            'link' => $link,
        ];
    }
}
