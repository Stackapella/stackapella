<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Console\Commands\CheckTemplates;
use App\Support\Templates\ArrangementCheck;
use App\Support\Templates\TemplateRules;
use Tests\TestCase;

/**
 * The arranger's checks (docs/arrangements-final-review.md) on every official template: hard errors
 * (broken data) fail; the rest is advice for the musician, printed with
 * TEMPLATE_CHECK_REPORT=1 (and by `php artisan app:templates:check --advice`).
 */
final class ArrangementCheckTest extends TestCase
{
    public function test_no_official_template_has_a_hard_error(): void
    {
        $templates = CheckTemplates::templates();
        $this->assertGreaterThanOrEqual(22, count($templates));
        $advice = 0;

        foreach ($templates as $id => $template) {
            $found = app(ArrangementCheck::class)->check($template);
            $this->assertSame([], $found['hard'], "{$id} has hard errors.");
            $advice += count($found['advice']);

            if (getenv('TEMPLATE_CHECK_REPORT') !== false) {
                foreach ($found['advice'] as $line) {
                    fwrite(STDERR, "{$id}: {$line}\n");
                }
            }
        }

        if (getenv('TEMPLATE_CHECK_REPORT') !== false) {
            fwrite(STDERR, "{$advice} pieces of advice across ".count($templates)." songs.\n");
        }
    }

    /**
     * A small 4/4 template: the two voices given, a C major chord throughout.
     *
     * @param  array<string, list<array<string, mixed>>>  $parts  notes by part key
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function template(array $parts, array $with = []): array
    {
        return [
            'id' => 'test', 'timeSignature' => '4/4', 'pickupBeats' => 0, 'sections' => [['name' => 'Verse', 'startBeat' => 0, 'endBeat' => 8]],
            'chords' => [['beat' => 0, 'root' => 0, 'quality' => 'maj', 'bass' => null]],
            'parts' => array_map(static fn (string $key, array $notes): array => ['key' => $key, 'guide' => ['type' => 'notes', 'notes' => $notes]], array_keys($parts), $parts),
            ...$with,
        ];
    }

    /** @return list<array<string, mixed>> notes of `length` beats each, from beat 0 */
    private static function line(array $pitches, float $length = 2, string $first = 'la'): array
    {
        return array_map(static fn (int $i, int $p): array => ['beat' => $i * $length, 'length' => $length, 'pitch' => $p, ...($i === 0 ? ['lyric' => $first, 'br' => true] : ['lyric' => 'la'])], array_keys($pitches), $pitches);
    }

    public function test_barbershop_has_its_own_ranges_with_the_tenor_above_the_lead(): void
    {
        $check = app(ArrangementCheck::class);
        $parts = ['tenor' => self::line([69, 69, 69, 69]), 'lead' => self::line([64, 64, 64, 64]), 'baritone' => self::line([60, 60, 60, 60]), 'bass' => self::line([48, 48, 48, 48])];

        $this->assertTrue(TemplateRules::isBarbershop($this->template($parts)));
        $this->assertSame([], $check->check($this->template($parts))['hard']);
        // A barbershop tenor at A4 is comfortable; an SATB tenor there is past its comfort.
        $range = fn (array $t): array => array_values(array_filter($check->check($t)['advice'], fn (string $a): bool => str_contains($a, 'amateur tenor range')));
        $this->assertSame([], $range($this->template($parts)));
        $this->assertNotSame([], $range($this->template(['soprano' => self::line([72, 72, 72, 72]), 'alto' => self::line([65, 65, 65, 65]), 'tenor' => self::line([69, 69, 69, 69]), 'bass' => self::line([48, 48, 48, 48])])));
        $this->assertFalse(TemplateRules::isBarbershop($this->template(['soprano' => self::line([67, 67, 67, 67]), 'tenor' => self::line([69, 69, 69, 69])])));
    }

    public function test_broken_data_is_a_hard_error(): void
    {
        $check = app(ArrangementCheck::class);
        $good = self::line([67, 67, 67, 67]);

        $this->assertSame([], $check->check($this->template(['soprano' => $good, 'alto' => self::line([64, 64, 64, 64])]))['hard']);

        // Overlapping notes, a part that stops short, a pitch far out of range, a phrase without words.
        $overlap = [...$good];
        $overlap[1]['beat'] = 1;
        $short = self::line([64, 64, 64]);
        $high = self::line([67, 67, 67, 90]);
        $silent = $good;
        unset($silent[0]['lyric']);

        foreach ([[$overlap, 'overlaps'], [$high, 'hard range'], [$silent, 'no syllable']] as [$soprano, $why]) {
            $hard = $check->check($this->template(['soprano' => $soprano, 'alto' => self::line([64, 64, 64, 64])]))['hard'];
            $this->assertNotSame([], array_filter($hard, static fn (string $l): bool => str_contains($l, $why)), $why);
        }

        $this->assertStringContainsString("don't line up", implode(' ', $check->check($this->template(['soprano' => $good, 'alto' => $short]))['hard']));
    }

    public function test_the_musicians_advice(): void
    {
        $check = app(ArrangementCheck::class);
        $advice = static fn (array $t): string => implode("\n", $check->check($t)['advice']);

        // Parallel fifths: both voices step up from a fifth to a fifth.
        $this->assertStringContainsString('parallel fifths', $advice($this->template(['soprano' => self::line([67, 69, 67, 67]), 'alto' => self::line([60, 62, 60, 60])])));
        // The same through quick passing notes isn't.
        $passing = $this->template(['soprano' => self::line([67, 69, 67, 67], 0.5), 'alto' => self::line([60, 62, 60, 60], 0.5)], ['sections' => [['name' => 'V', 'startBeat' => 0, 'endBeat' => 2]]]);
        $this->assertStringNotContainsString('parallel', $advice($passing));

        // A leap over a sixth, a note out of the amateur range, a long note off the chord.
        $this->assertStringContainsString('over a sixth', $advice($this->template(['soprano' => self::line([60, 72, 72, 72]), 'alto' => self::line([55, 55, 55, 55])])));
        $this->assertStringContainsString('amateur soprano range', $advice($this->template(['soprano' => self::line([79, 79, 79, 79]), 'alto' => self::line([64, 64, 64, 64])])));
        $this->assertStringContainsString("isn't in C maj", $advice($this->template(['soprano' => self::line([69, 67, 67, 67]), 'alto' => self::line([64, 64, 64, 64])])));

        // The duet sings C and G over C major: no E, so no third.
        $duet = $this->template(['soprano' => self::line([67, 67, 67, 67]), 'alto' => self::line([60, 60, 60, 60])], ['voicings' => ['2' => ['soprano', 'alto']]]);
        $this->assertStringContainsString('without its third', $advice($duet));
        $withThird = $this->template(['soprano' => self::line([67, 67, 67, 67]), 'alto' => self::line([64, 64, 64, 64])], ['voicings' => ['2' => ['soprano', 'alto']]]);
        $this->assertStringNotContainsString('without its third', $advice($withThird));
    }

    public function test_the_smaller_voicings_advice(): void
    {
        $check = app(ArrangementCheck::class);
        $advice = static fn (array $t): string => implode("\n", $check->check($t)['advice']);
        $duet = ['voicings' => ['2' => ['soprano', 'alto']]];

        // The duet's G4 over D4 on the downbeats is a bare fourth; G4 over E4 isn't.
        $this->assertStringContainsString('a bare fourth (G4 over D4)', $advice($this->template(['soprano' => self::line([67, 67, 67, 67]), 'alto' => self::line([62, 62, 62, 62])], $duet)));
        $this->assertStringNotContainsString('duet:', $advice($this->template(['soprano' => self::line([67, 67, 67, 67]), 'alto' => self::line([64, 64, 64, 64])], $duet)));
        // Over C6 the tune's A4 is the sixth: the alto's E4 under it is fine.
        $sixth = ['chords' => [['beat' => 0, 'root' => 0, 'quality' => 'maj6', 'bass' => null]], ...$duet];
        $this->assertStringNotContainsString('duet:', $advice($this->template(['soprano' => self::line([69, 69, 69, 69]), 'alto' => self::line([64, 64, 64, 64])], $sixth)));
        // A second on a downbeat clashes; the same on quick off-beat notes passes.
        $this->assertStringContainsString('a second (D4 over C4)', $advice($this->template(['soprano' => self::line([62, 64, 64, 64]), 'alto' => self::line([60, 60, 60, 60])], $duet)));
        $this->assertStringNotContainsString('a second', $advice($this->template(['soprano' => self::line([64, 62, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64, 64], 0.5), 'alto' => self::line(array_fill(0, 16, 60), 0.5)], $duet)));

        // The trio's last chord without its E, where the C major began earlier; with it, none.
        $trio = ['voicings' => ['3' => ['soprano', 'alto', 'bass']]];
        $bass = self::line([48, 48, 48, 48]);
        $this->assertStringContainsString('the last chord, C maj, without its third', $advice($this->template(['soprano' => self::line([64, 64, 64, 67]), 'alto' => self::line([55, 55, 55, 60]), 'bass' => $bass], $trio)));
        $this->assertStringNotContainsString('the last chord', $advice($this->template(['soprano' => self::line([64, 64, 64, 67]), 'alto' => self::line([55, 55, 55, 64]), 'bass' => $bass], $trio)));

        // An alto's leap of a seventh into a new phrase is advice; the tune's own leap there isn't.
        $phrases = self::line([60, 60, 71, 71]);
        $phrases[2]['br'] = true;
        $this->assertStringContainsString('alto 1.3 (2): C4 to B4 into a new phrase', $advice($this->template(['soprano' => self::line([76, 76, 76, 76]), 'alto' => $phrases])));
        $this->assertStringNotContainsString('into a new phrase', $advice($this->template(['soprano' => $phrases, 'alto' => self::line([55, 55, 55, 55])])));
    }

    public function test_only_a_real_round_is_exempt(): void
    {
        $check = app(ArrangementCheck::class);
        $advice = static fn (array $t): string => implode("\n", $check->check($t)['advice']);
        $duet = ['voicings' => ['2' => ['soprano', 'alto']], 'sections' => [['name' => 'V', 'startBeat' => 0, 'endBeat' => 10]]];
        $later = static fn (array $notes): array => array_map(static fn (array $n): array => [...$n, 'beat' => $n['beat'] + 2], $notes);

        // The same tune, two beats later: a round, so its fourths are its own.
        $this->assertStringNotContainsString('duet:', $advice($this->template(['soprano' => self::line([67, 67, 67, 67, 67]), 'alto' => $later(self::line([67, 67, 67, 67]))], $duet)));
        // A solo pickup before a harmony part isn't a round: the fourths count.
        $this->assertStringContainsString('a bare fourth', $advice($this->template(['soprano' => self::line([67, 67, 67, 67, 67]), 'alto' => $later(self::line([62, 62, 62, 62]))], $duet)));
    }

    public function test_the_command_counts_the_hard_errors_and_the_advice(): void
    {
        $this->artisan('app:templates:check')->expectsOutputToContain('0 hard errors')->assertSuccessful();
        $this->artisan('app:templates:check', ['song' => 'silent-night'])->expectsOutputToContain('silent-night')->assertSuccessful();
        $this->artisan('app:templates:check', ['song' => 'no-such-song'])->assertFailed();
    }
}
