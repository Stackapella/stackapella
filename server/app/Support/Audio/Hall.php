<?php

declare(strict_types=1);

namespace App\Support\Audio;

/**
 * The room "Make it sound good" puts a mix in (web/js/polish.js, impulseResponse), made the same
 * way here for the choir's mix (N6): stereo, decaying and darkening noise after a short gap, from
 * the same seeded generator, so the choir sounds like the app's own hall.
 */
final class Hall
{
    /** polish.js ROOMS.hall. */
    public const DECAY = 2.1;

    public const BRIGHT = 0.42;

    public const WET = 0.24;

    public const PREDELAY = 0.022;

    /**
     * The impulse response as a 16-bit stereo WAV at this rate, written to $path.
     */
    public static function write(string $path, int $sampleRate): void
    {
        [$left, $right] = [self::channel(11, $sampleRate), self::channel(23, $sampleRate)];
        $peak = 1e-9;

        foreach ([...$left, ...$right] as $v) {
            $peak = max($peak, abs($v));
        }

        $data = '';

        foreach ($left as $i => $l) {
            $data .= pack('v2', self::sample($l / $peak), self::sample($right[$i] / $peak));
        }

        $header = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 2, $sampleRate, $sampleRate * 4, 4, 16).'data'.pack('V', strlen($data));
        file_put_contents($path, $header.$data);
    }

    /**
     * One channel, as polish.js makes it (before its unit-energy scaling, which the mix's
     * `wet` level stands in for).
     *
     * @return list<float>
     */
    public static function channel(int $seed, int $sampleRate): array
    {
        $random = self::seeded($seed);
        $length = (int) round((self::PREDELAY + self::DECAY * 1.2) * $sampleRate);
        $gap = (int) round(self::PREDELAY * $sampleRate);
        $out = array_fill(0, $length, 0.0);
        $low = 0.0;

        for ($i = $gap; $i < $length; $i++) {
            $t = ($i - $gap) / $sampleRate;
            $envelope = 10 ** ((-3 * $t) / self::DECAY);
            $smoothing = self::BRIGHT * exp(-$t / self::DECAY);
            $low += ($random() * 2 - 1 - $low) * max(0.02, $smoothing);
            $out[$i] = $low * $envelope;
        }

        for ($i = $gap; $i < min($length, $gap + (int) ($sampleRate * 0.002)); $i++) {
            $out[$i] *= ($i - $gap) / ($sampleRate * 0.002);
        }

        $energy = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $out)));

        return array_values(array_map(static fn (float $v): float => $energy > 0 ? $v / $energy : 0.0, $out));
    }

    /**
     * polish.js's seeded(): mulberry32, in 32-bit unsigned arithmetic.
     *
     * @return callable(): float
     */
    private static function seeded(int $seed): callable
    {
        $s = $seed & 0xFFFFFFFF;

        return static function () use (&$s): float {
            $s = ($s + 0x6D2B79F5) & 0xFFFFFFFF;
            $r = self::imul($s ^ ($s >> 15), 1 | $s);
            $r = ($r ^ ($r + self::imul($r ^ ($r >> 7), 61 | $r))) & 0xFFFFFFFF;

            return (($r ^ ($r >> 14)) & 0xFFFFFFFF) / 4294967296;
        };
    }

    /**
     * Math.imul: the low 32 bits of the product, unsigned.
     */
    private static function imul(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;
        $low = (($a & 0xFFFF) * $b) & 0xFFFFFFFF;
        $high = ((($a >> 16) * $b) & 0xFFFF) << 16;

        return ($low + $high) & 0xFFFFFFFF;
    }

    private static function sample(float $v): int
    {
        return ((int) round(max(-1.0, min(1.0, $v)) * 32767)) & 0xFFFF;
    }

    /**
     * Where each part sits (polish.js spread): a little left and right by part, the lowest in
     * the middle.
     */
    public static function spread(int $index, int $count): float
    {
        if ($count < 2 || $index === $count - 1) {
            return 0.0;
        }

        return ($index % 2 === 0 ? -1 : 1) * (0.18 + 0.08 * intdiv($index, 2));
    }
}
