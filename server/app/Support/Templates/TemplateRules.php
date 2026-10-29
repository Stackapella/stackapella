<?php

declare(strict_types=1);

namespace App\Support\Templates;

/**
 * The template rules the JSON Schema subset can't say (ranges, order, voicings), the PHP twin of
 * validateTemplate() in web/js/templates.js. Both sides refuse the same files: the shape is
 * contract/schemas/template.json, and these are the rest.
 */
final class TemplateRules
{
    /** How a song or a part uses vibrato (docs/vibrato-plan.md); free when unsaid. */
    public const STYLES = ['straight', 'free', 'vibrato'];

    public const TIME_SIGNATURES = ['4/4', '3/4', '6/8', '2/4'];

    private const EPSILON = 1e-9;

    /** Free part keys (docs/instrument-parts-plan.md), and at most six parts. */
    public const KEY = '/^[a-z][a-z0-9-]{0,39}$/';

    public const MAX_PARTS = 6;

    /** Official templates stay SATB, all voices. */
    public const OFFICIAL_KEYS = ['soprano', 'alto', 'tenor', 'bass'];

    /** True barbershop (Hillel: "Yes, true barbershop"): the lead sings the tune, the tenor above it. */
    public const BARBERSHOP_KEYS = ['tenor', 'lead', 'baritone', 'bass'];

    /**
     * Whether a template is barbershop: any part keyed lead or baritone.
     *
     * @param  array<string, mixed>  $t
     */
    public static function isBarbershop(array $t): bool
    {
        foreach ((array) ($t['parts'] ?? []) as $part) {
            if (is_array($part) && in_array($part['key'] ?? null, ['lead', 'baritone'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The official catalogue's own rule, beside problems(): the four SATB parts, or the four
     * barbershop parts (tenor, lead, baritone, bass), never a mix, all voices.
     *
     * @param  array<string, mixed>  $t
     * @return list<string>
     */
    public function officialProblems(array $t): array
    {
        $problems = [];
        $barbershop = self::isBarbershop($t);
        $keys = $barbershop ? self::BARBERSHOP_KEYS : self::OFFICIAL_KEYS;

        foreach ((array) ($t['parts'] ?? []) as $part) {
            $key = is_array($part) ? ($part['key'] ?? null) : null;

            if (! in_array($key, $keys, true) || (is_array($part) && ($part['kind'] ?? 'voice') !== 'voice')) {
                $problems[] = 'part "'.(is_scalar($key) ? $key : '').'": '.($barbershop
                    ? 'a barbershop template is tenor, lead, baritone and bass, all voices, never mixed with soprano or alto'
                    : 'official templates are soprano, alto, tenor and bass (or barbershop\'s tenor, lead, baritone and bass), all voices');
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $t  the template, decoded as arrays
     * @return list<string> what's wrong, in the app's wording; empty when it passes
     */
    public function problems(array $t): array
    {
        $problems = [];
        $need = static function (bool $ok, string $message) use (&$problems): void {
            if (! $ok) {
                $problems[] = $message;
            }
        };

        $need(is_string($t['id'] ?? null) && preg_match('/^[a-z0-9-]+$/', $t['id']) === 1, 'id must be lowercase letters, digits and dashes');
        $need(is_int($t['version'] ?? null) && $t['version'] >= 1, 'version must be a whole number from 1');
        $need(is_string($t['title'] ?? null) && trim($t['title']) !== '', 'title is missing');
        $need(is_bool($t['reviewed'] ?? null), 'reviewed must be true or false');
        $need(is_numeric($t['tempo'] ?? null) && $t['tempo'] >= 30 && $t['tempo'] <= 300, 'tempo must be 30 to 300');
        $need(is_string($t['key'] ?? null), 'key is missing');
        $need(in_array($t['timeSignature'] ?? null, self::TIME_SIGNATURES, true), 'timeSignature must be one of '.implode(', ', self::TIME_SIGNATURES));
        $need(is_array($t['licence'] ?? null), 'licence is missing');
        $need(! isset($t['style']) || in_array($t['style'], self::STYLES, true), 'style must be one of '.implode(', ', self::STYLES));

        $perBar = self::beatsPerBar((string) ($t['timeSignature'] ?? ''));
        $need(is_numeric($t['pickupBeats'] ?? null) && $t['pickupBeats'] >= 0 && $t['pickupBeats'] < $perBar, 'pickupBeats must be 0 or more, and less than a bar');

        $parts = is_array($t['parts'] ?? null) ? $t['parts'] : [];
        $need($parts !== [], 'parts are missing');
        $need(count($parts) <= self::MAX_PARTS, 'parts: at most '.self::MAX_PARTS);

        $keys = [];
        $melody = null;

        foreach ($parts as $part) {
            $key = is_array($part) ? ($part['key'] ?? null) : null;
            $name = 'part "'.(is_scalar($key) ? $key : '').'"';
            $need(is_string($key) && ! in_array($key, $keys, true), "{$name}: key missing or repeated");
            $need(is_string($key) && preg_match(self::KEY, $key) === 1, "{$name}: key must be lower-case letters, digits and dashes, starting with a letter (up to 40)");
            $need(! isset($part['kind']) || in_array($part['kind'], ['voice', 'instrument'], true), "{$name}: kind must be voice or instrument");
            $need(! isset($part['style']) || in_array($part['style'], self::STYLES, true), "{$name}: style must be one of ".implode(', ', self::STYLES));
            $keys[] = $key;
            $melody ??= ($part['melody'] ?? false) === true ? $key : null;
            $need(is_string($part['name'] ?? null) && $part['name'] !== '' && mb_strlen($part['name']) <= 40, "{$name}: name is missing or over 40 characters");

            $range = is_array($part['range'] ?? null) ? $part['range'] : null;
            $need($range !== null && ($range['low'] ?? 0) <= ($range['high'] ?? -1), "{$name}: range needs low <= high");

            $notes = is_array($part['guide']['notes'] ?? null) ? $part['guide']['notes'] : [];
            $need(($part['guide']['type'] ?? null) === 'notes' && $notes !== [], "{$name}: guide needs notes");

            $end = -INF;
            foreach (array_values($notes) as $i => $note) {
                $at = "{$name}, note ".($i + 1);
                $beat = $note['beat'] ?? null;
                $length = $note['length'] ?? null;
                $pitch = $note['pitch'] ?? null;
                $need(is_numeric($beat) && $beat >= 0, "{$at}: beat must be 0 or more");
                $need(is_numeric($length) && $length > 0, "{$at}: length must be more than 0");
                $need(is_int($pitch), "{$at}: pitch must be a MIDI note number");
                $need(is_numeric($beat) && $beat >= $end - self::EPSILON, "{$at}: starts before the previous note ends (one note at a time, in order)");

                if ($range !== null && is_int($pitch)) {
                    $need($pitch >= ($range['low'] ?? 0) && $pitch <= ($range['high'] ?? 127), "{$at}: pitch {$pitch} is outside the part's range");
                }

                $end = (float) $beat + (float) $length;
            }
        }

        foreach ((array) ($t['order'] ?? []) as $key) {
            $need(in_array($key, $keys, true), "order names a part that doesn't exist: {$key}");
        }

        foreach ((array) ($t['voicings'] ?? []) as $count => $list) {
            $list = is_array($list) ? $list : [];
            $need(preg_match('/^[1-8]$/', (string) $count) === 1 && count($list) === (int) $count, "voicings: \"{$count}\" must list exactly {$count} parts");
            $need(count(array_unique($list)) === count($list) && array_diff($list, $keys) === [], "voicings: \"{$count}\" names a missing or repeated part");
            $need($melody === null || in_array($melody, $list, true), "voicings: \"{$count}\" must include the melody ({$melody})");
        }

        $total = $problems === [] ? self::lengthBeats($parts) : 0.0;

        foreach ((array) ($t['sections'] ?? []) as $section) {
            $name = 'section "'.($section['name'] ?? '').'"';
            $start = (float) ($section['startBeat'] ?? -1);
            $stop = (float) ($section['endBeat'] ?? -1);
            $need($start >= 0 && $stop > $start && ($total === 0.0 || $stop <= $total + self::EPSILON), "{$name}: must lie inside the song");

            $previous = $start;
            foreach ((array) ($section['phrases'] ?? []) as $phrase) {
                [$from, $to] = [(float) ($phrase[0] ?? -1), (float) ($phrase[1] ?? -1)];
                $need($from >= $previous - self::EPSILON && $to > $from && $to <= $stop + self::EPSILON, "{$name}: phrases must be in order and inside the section");
                $previous = $to;
            }
        }

        // Chords underneath (optional): each lasts to the next one's beat, so they must be in order,
        // and start inside the song. The schema checks their shape.
        $previous = -1.0;
        foreach ((array) ($t['chords'] ?? []) as $i => $chord) {
            $beat = is_array($chord) && is_numeric($chord['beat'] ?? null) ? (float) $chord['beat'] : -1.0;
            $need($beat >= 0 && $beat > $previous && ($total === 0.0 || $beat < $total), "chord {$i}: beats must be ascending, from 0, inside the song");
            $previous = $beat;
        }

        $this->builderProblems($t['builder'] ?? null, $need);

        return $problems;
    }

    /**
     * The song builder's record (optional; its shape is the schema's): numerals short, at most 64
     * bars a section and 16 sections, the mix and swing from 0 to 1.
     *
     * @param  callable(bool, string): void  $need
     */
    private function builderProblems(mixed $builder, callable $need): void
    {
        if (! is_array($builder)) {
            return;
        }

        $barsOk = static function (mixed $bars): bool {
            if (! is_array($bars) || $bars === [] || count($bars) > 64) {
                return false;
            }

            foreach ($bars as $bar) {
                foreach (is_array($bar) && $bar !== [] ? $bar : [null] as $numeral) {
                    if (! is_string($numeral) || $numeral === '' || mb_strlen($numeral) > 12) {
                        return false;
                    }
                }
            }

            return true;
        };

        $progression = $builder['progression'] ?? null;
        $need(! is_array($progression) || $barsOk($progression['bars'] ?? null), 'builder: the progression needs 1 to 64 bars of numerals (up to 12 characters each)');
        $need(! is_array($progression) || ! isset($progression['swing']) || (is_numeric($progression['swing']) && $progression['swing'] >= 0 && $progression['swing'] <= 1), 'builder: swing must be 0 to 1');

        foreach ((array) ($builder['mix'] ?? []) as $level) {
            $need(is_numeric($level) && $level >= 0 && $level <= 1, 'builder: each mix level must be 0 to 1');
        }

        $sections = is_array($builder['sections'] ?? null) ? $builder['sections'] : [];
        $need(count($sections) <= 16, 'builder: at most 16 sections');

        foreach ($sections as $section) {
            $need(is_array($section) && $barsOk($section['bars'] ?? null), 'builder: each section needs at most 64 bars of numerals (up to 12 characters each)');
        }
    }

    public static function beatsPerBar(string $timeSignature): int
    {
        return ['4/4' => 4, '3/4' => 3, '6/8' => 2, '2/4' => 2][$timeSignature] ?? 4;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    public static function lengthBeats(array $parts): float
    {
        $end = 0.0;

        foreach ($parts as $part) {
            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                $end = max($end, (float) $note['beat'] + (float) $note['length']);
            }
        }

        return $end;
    }
}
