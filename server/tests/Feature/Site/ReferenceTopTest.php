<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reference page in Hillel's width design (2026-11-11): the lead beside the heading, the card beside the guide, and the
 * family's other pages four across, each numbered (an interval by its semitones, else its place) with its line.
 */
final class ReferenceTopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_an_interval_puts_its_card_beside_its_guide_and_numbers_its_family_by_semitones(): void
    {
        $html = (string) $this->get('/intervals/perfect-fifth')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<section class="s-wrap s-thead s-ref-head".*?<h1 id="page-h">.*?</div>\s*<p class="s-ref-lead">#s', $html);
        $this->assertMatchesRegularExpression('#<div class="s-wrap s-ref-body">\s*<section class="s-ref-cardcol".*?data-tool="ref-intervals".*?<div class="s-ref-prose">\s*<section class="s-tool-guide"#s', $html);
        $this->assertStringNotContainsString('s-spart-chip', $html);
        // Every other interval, numbered by its semitones; the fifth itself isn't listed.
        $this->assertSame(11, substr_count($html, 'class="s-card s-ref-fam"'));
        $this->assertStringContainsString('<span class="s-ref-fam-n" aria-hidden="true">8</span>', $html);
        $this->assertStringNotContainsString('href="/intervals/perfect-fifth" class="s-card s-ref-fam"', $html);
    }

    public function test_a_reference_index_puts_its_lead_beside_its_heading_and_its_guide_in_rows(): void
    {
        foreach (['/intervals', '/chords', '/voice-types'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#<section class="s-wrap s-thead s-ref-head s-ref-head-ix".*?<h1 id="page-h">.*?</div>\s*<p class="s-ref-lead">#s', $html, $path);
            $this->assertMatchesRegularExpression('#<div class="s-wrap s-sec2 s-tool-guides">\s*<section class="s-tool-guide"#', $html, $path);
        }
    }

    public function test_a_chord_and_a_voice_type_are_numbered_by_their_place_with_their_line(): void
    {
        $rows = Reference::rows('chords');
        $this->assertSame('1', $rows[0]['n']);
        $this->assertSame($rows[0]['cells'][1], $rows[0]['line']);

        $voices = Reference::rows('voice-types');
        $this->assertSame((string) count($voices), $voices[count($voices) - 1]['n']);
        $this->assertSame($voices[0]['cells'][0], $voices[0]['line']);

        // An interval with no song going up has no line rather than a dash.
        foreach (Reference::rows('intervals') as $row) {
            $this->assertNotSame('-', $row['line']);
        }
        // A voice type has its own page (VoiceTypePageTest); a chord keeps the family grid.
        $this->get('/chords/minor')->assertOk()->assertSee('class="s-card s-ref-fam"', false);
    }
}
