<?php

declare(strict_types=1);

namespace App\Support\Videos;

/**
 * A recording's sync map checked against the song it was made for, as web/js/sync-map.js's mapProblems checks it, with
 * the same codes (docs/youtube-sync-plan.md): the form's sections exist, shifts and cents are in range, a gap has
 * beats, and the anchors move on in both the video's time and the performance's beats, inside the performance.
 */
final class SyncMap
{
    private const EPS = 1e-6;

    /**
     * @param  array<string, mixed>  $template  the template version's data
     * @param  list<array<string, mixed>>  $form
     * @param  list<list<float|int>>  $anchors
     * @return list<string>
     */
    public static function problems(array $template, array $form, array $anchors): array
    {
        $sections = is_array($template['sections'] ?? null) ? array_values($template['sections']) : [];
        $problems = [];
        if ($form === []) {
            $problems[] = 'form.empty';
        }
        $length = 0.0;
        foreach ($form as $segment) {
            $kind = $segment['kind'] ?? null;
            if ($kind === 'section') {
                $index = $segment['section'] ?? null;
                $section = is_int($index) && is_array($sections[$index] ?? null) ? $sections[$index] : null;
                if ($section === null) {
                    $problems[] = 'form.section';
                } else {
                    $length += (float) ($section['endBeat'] ?? 0) - (float) ($section['startBeat'] ?? 0);
                }
                $shift = $segment['shift'] ?? 0;
                if (! is_int($shift) || abs($shift) > 11) {
                    $problems[] = 'form.shift';
                }
                $cents = $segment['cents'] ?? 0;
                if (! is_numeric($cents) || abs((float) $cents) > 50) {
                    $problems[] = 'form.cents';
                }
            } elseif ($kind === 'gap') {
                $beats = $segment['beats'] ?? 0;
                if (! is_numeric($beats) || (float) $beats <= 0) {
                    $problems[] = 'form.gap';
                } else {
                    $length += (float) $beats;
                }
            } else {
                $problems[] = 'form.kind';
            }
        }
        if ($form !== [] && ! in_array('section', array_column($form, 'kind'), true)) {
            $problems[] = 'form.sections';
        }
        if (count($anchors) < 2) {
            $problems[] = 'anchors.few';
        } else {
            $limit = $problems === [] ? $length : INF;
            foreach ($anchors as $i => $anchor) {
                [$t, $b] = [(float) ($anchor[0] ?? -1), (float) ($anchor[1] ?? -1)];
                if ($t < 0 || $b < 0 || $b > $limit + self::EPS) {
                    $problems[] = 'anchors.value';
                    break;
                }
                if ($i > 0 && ($t <= (float) $anchors[$i - 1][0] || $b <= (float) $anchors[$i - 1][1])) {
                    $problems[] = 'anchors.order';
                    break;
                }
            }
        }

        return array_values(array_unique($problems));
    }
}
