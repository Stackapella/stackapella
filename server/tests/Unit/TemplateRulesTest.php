<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Templates\TemplateRules;
use Tests\TestCase;

/**
 * The PHP twin of web/js/templates.js's validateTemplate(): the official templates pass, and the
 * same broken files are refused with the same words.
 */
final class TemplateRulesTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function amazingGrace(): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
    }

    public function test_every_official_template_passes(): void
    {
        $files = array_filter([...glob(dirname(base_path()).'/web/templates/*.json') ?: [], ...glob(database_path('templates/*.json')) ?: []], static fn (string $f): bool => basename($f) !== 'index.json');

        foreach ($files as $file) {
            $this->assertSame([], (new TemplateRules)->problems((array) json_decode((string) file_get_contents($file), true)), basename($file));
        }
    }

    public function test_broken_templates_are_refused_in_the_apps_words(): void
    {
        $rules = new TemplateRules;

        $t = $this->amazingGrace();
        $t['tempo'] = 500;
        $this->assertSame(['tempo must be 30 to 300'], $rules->problems($t));

        $t = $this->amazingGrace();
        $t['parts'][0]['guide']['notes'][1]['beat'] = 0.5;
        $this->assertContains('part "soprano", note 2: starts before the previous note ends (one note at a time, in order)', $rules->problems($t));

        $t = $this->amazingGrace();
        $t['parts'][1]['guide']['notes'][0]['pitch'] = 20;
        $this->assertContains('part "'.$t['parts'][1]['key'].'", note 1: pitch 20 is outside the part\'s range', $rules->problems($t));

        $t = $this->amazingGrace();
        $t['pickupBeats'] = 3;
        $this->assertSame(['pickupBeats must be 0 or more, and less than a bar'], $rules->problems($t));

        $t = $this->amazingGrace();
        $t['sections'][0]['endBeat'] = 1000;
        $this->assertSame(['section "Verse 1": must lie inside the song'], $rules->problems($t));

        $t = $this->amazingGrace();
        $t['voicings'] = ['2' => ['alto', 'bass']];
        $this->assertSame(['voicings: "2" must include the melody (soprano)'], $rules->problems($t));

        $t = $this->amazingGrace();
        $t['order'][] = 'baritone';
        $this->assertSame(['order names a part that doesn\'t exist: baritone'], $rules->problems($t));
    }

    public function test_chords_must_be_in_order_and_inside_the_song(): void
    {
        $rules = new TemplateRules;
        $t = $this->amazingGrace();

        $t['chords'] = [['beat' => 0, 'root' => 7, 'quality' => 'maj', 'bass' => null], ['beat' => 3, 'root' => 0, 'quality' => 'maj', 'bass' => 4]];
        $this->assertSame([], $rules->problems($t));

        $t['chords'] = [['beat' => 3, 'root' => 7, 'quality' => 'maj', 'bass' => null], ['beat' => 3, 'root' => 0, 'quality' => 'maj', 'bass' => null]];
        $this->assertSame(['chord 1: beats must be ascending, from 0, inside the song'], $rules->problems($t));

        $t['chords'] = [['beat' => 100000, 'root' => 7, 'quality' => 'maj', 'bass' => null]];
        $this->assertSame(['chord 0: beats must be ascending, from 0, inside the song'], $rules->problems($t));
    }
}
