<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

/**
 * A clip with no person in it, for staff's end-to-end test of the choir worker (app:own-choir:test-request): a part's
 * own written notes, frame 0 at beat 0, played as five partials (1, 0.5, 0.3, 0.2 and 0.1 of the fundamental) with a
 * 5.5 Hz vibrato of 25 cents and 30 ms soft edges, as the worker's own synth_take does, so its fit check accepts it.
 * A plain 16-bit mono WAV with a 44-byte header, quiet at both edges.
 */
final class SyntheticClip
{
    private const PARTIALS = [1.0, 0.5, 0.3, 0.2, 0.1];

    /** Silence before beat 0 and after the last note, as a real recording's count-in and its stop. */
    public const LEAD_SECONDS = 0.5;

    /**
     * The clip and where beat 0 falls in it (frames): 0.5 s of silence, then the part from beat 0 up to the last note
     * that ends at least 0.5 s before $seconds and is followed by a rest of 0.2 s or more, then 0.5 s of silence, so the
     * worker's voice step finds quiet at both edges (ai-modal, 2026-11-22: a cut mid-note reads as a breath).
     *
     * @param  array<string, mixed>  $data  a template version's data
     * @return array{wav: string, offsetFrames: int}
     */
    public static function make(array $data, string $partKey, float $seconds = 24.0, int $rate = 48000): array
    {
        $part = collect((array) ($data['parts'] ?? []))->first(static fn (mixed $p): bool => is_array($p) && ($p['key'] ?? null) === $partKey);
        $notes = array_values(array_filter((array) (is_array($part) ? ($part['guide']['notes'] ?? []) : []), static fn (mixed $n): bool => is_array($n) && isset($n['pitch'])));
        usort($notes, static fn (array $a, array $b): int => $a['beat'] <=> $b['beat']);
        $beat = 60 / max(1.0, (float) ($data['tempo'] ?? 0));
        $lead = (int) round(self::LEAD_SECONDS * $rate);

        // Where the singing stops: the last note ending in time and followed by a rest.
        $end = 0.0;
        foreach ($notes as $i => $note) {
            $noteEnd = ((float) $note['beat'] + (float) ($note['length'] ?? 0)) * $beat;
            $next = isset($notes[$i + 1]) ? (float) $notes[$i + 1]['beat'] * $beat : INF;

            if ($noteEnd <= $seconds - self::LEAD_SECONDS && $next - $noteEnd >= 0.2) {
                $end = $noteEnd;
            }
        }

        $frames = $lead + (int) round($end * $rate) + $lead;
        $samples = array_fill(0, max(1, $frames), 0.0);
        $edge = (int) round(0.03 * $rate);

        foreach ($notes as $note) {
            $from = $lead + (int) round((float) $note['beat'] * $beat * $rate);
            $to = min($lead + (int) round($end * $rate), $lead + (int) round(((float) $note['beat'] + (float) ($note['length'] ?? 0)) * $beat * $rate));
            $fundamental = 440 * 2 ** (((float) $note['pitch'] - 69) / 12);
            $phases = array_fill(0, count(self::PARTIALS), 0.0);

            for ($i = $from; $i < $to; $i++) {
                $t = ($i - $lead) / $rate;
                $f = $fundamental * 2 ** ((25 / 1200) * sin(2 * M_PI * 5.5 * $t));
                $envelope = min(1.0, ($i - $from) / max(1, $edge), ($to - $i) / max(1, $edge));
                $value = 0.0;

                foreach (self::PARTIALS as $k => $amplitude) {
                    $phases[$k] += 2 * M_PI * $f * ($k + 1) / $rate;
                    $value += $amplitude * sin($phases[$k]);
                }

                $samples[$i] = $value * $envelope;
            }
        }

        $peak = max(1e-9, ...array_map('abs', $samples));
        $pcm = pack('v*', ...array_map(static fn (float $s): int => (int) round($s / $peak * 0.5 * 32767) & 0xFFFF, $samples));

        return ['wav' => 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($pcm)).$pcm, 'offsetFrames' => $lead];
    }
}
