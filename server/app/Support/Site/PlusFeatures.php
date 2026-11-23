<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Flags;
use Illuminate\Support\Facades\Lang;

/**
 * Plus's features, once (Hillel's approved marketing review, 2026-11-22: retyped in ten places, they drifted): the
 * items that apply now, keyed on the switches (the choir line while own_choir is on, singing together while plus_teams
 * is on, seats' wording while choir_seats is), their numbers from config, in marketing's order (lang/<locale>/plus).
 * Every surface that lists Plus reads items() or sentence().
 */
final class PlusFeatures
{
    /**
     * @return list<array{0: string, 1: string}> each feature's gain and fact
     */
    public static function items(): array
    {
        $words = (array) Lang::get('plus.items');

        return array_values(array_map(static function (string $key) use ($words): array {
            $item = (array) ($words[$key] ?? ['', '']);
            $fact = $key === 'together' && Teams::seats() ? (string) Lang::get('plus.together_seats') : (string) ($item[1] ?? '');

            return [strtr((string) ($item[0] ?? ''), self::numbers()), strtr($fact, self::numbers())];
        }, self::keys()));
    }

    /** The same as one line: "Plus adds A, B and C." */
    public static function sentence(): string
    {
        return (string) __('plus.sentence', ['list' => self::list()]);
    }

    /** The features' short names joined ("A, B and C"), for a sentence that names Plus itself (a compare page's answer). */
    public static function list(): string
    {
        $short = (array) Lang::get('plus.short');
        $names = array_map(static fn (string $key): string => strtr((string) ($short[$key] ?? $key), self::numbers()), self::keys());
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).(string) Lang::get('plus.and').$last;
    }

    /**
     * What a surface's own words fill from the list (:plus_sentence, :plus_list, :plus_songs, :plus_minutes).
     *
     * @return array<string, string>
     */
    public static function replacements(): array
    {
        return [':plus_sentence' => self::sentence(), ':plus_list' => self::list(), ':plus_songs' => self::numbers()[':songs'], ':plus_minutes' => self::numbers()[':minutes']];
    }

    /**
     * The features that apply now, in marketing's order.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $flags = app(Flags::class);
        $on = static fn (string $flag): bool => $flags->exists($flag) && $flags->on($flag);

        return array_values(array_filter(array_keys((array) Lang::get('plus.items', [], 'en')), static fn (string $key): bool => match ($key) {
            'choir' => $on('own_choir'),
            'together' => Teams::on(),
            default => true,
        }));
    }

    /**
     * @return array<string, string>
     */
    private static function numbers(): array
    {
        $limits = self::limits();

        return [
            ':storage' => round($limits['storageBytes'] / 1024 ** 3).' GB',
            ':minutes' => (string) $limits['choirMinutes'],
            ':songs' => (string) $limits['choirSongs'],
        ];
    }

    /**
     * Plus's numbers, the site's words and the app's alike (auth.config `plusLimits`): the AI choir's part-minutes a
     * month, about how many songs that is, and the storage in bytes.
     *
     * @return array{choirMinutes: int, choirSongs: int, storageBytes: int}
     */
    public static function limits(): array
    {
        $minutes = (int) config('own_choir.plus_minutes');

        return [
            'choirMinutes' => $minutes,
            // A song is about four minutes of singing across its parts (marketing's estimate).
            'choirSongs' => max(1, intdiv($minutes, 4)),
            'storageBytes' => (int) config('usage.plans.plus.storage_bytes'),
        ];
    }
}
