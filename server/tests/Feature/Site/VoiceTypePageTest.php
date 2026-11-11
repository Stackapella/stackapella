<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\Reference;
use App\Support\Pages\VoiceTypePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A voice type's page in Hillel's design (2026-11-11, "Alto Voice page redesign"): its place among the six, its facts
 * and neighbours beside the bare keyboard card, the guide with its contents, and every type's range on one scale.
 */
final class VoiceTypePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_the_alto_page_has_its_place_facts_neighbour_bare_card_contents_and_ladder(): void
    {
        $html = (string) $this->get('/voice-types/alto')->assertOk()->getContent();

        $this->assertStringContainsString('<p class="s-teyebrow">Voice type · 4 of 6</p>', $html);
        $this->assertMatchesRegularExpression('#<dl class="s-spart-facts s-vt-facts">.*?F3 to D5.*?Alto.*?<a href="/voice-types/mezzo-soprano" class="s-lk">Mezzo</a>#s', $html);
        $this->assertStringContainsString('"bare":true', $html);
        $this->assertStringContainsString('<a href="#guide-2">Am I an alto or a mezzo-soprano?</a>', $html);
        $this->assertStringContainsString('Where alto sits', $html);
        // Six rungs, each in its type's part colour (VoiceTypePage::COLOURS), the alto's lit.
        $this->assertSame(6, preg_match_all('#class="s-vt-rung s-v[1-6]( s-vt-here)?"#', $html));
        $this->assertMatchesRegularExpression('#<a href="/voice-types/alto" class="s-vt-rung s-v2 s-vt-here"\s+aria-current="page"#', $html);
        $this->assertStringContainsString('<a href="/voice-types/mezzo-soprano" class="s-vt-rung s-v5"', $html);
        $this->assertStringContainsString('About altos', $html);
        // The intervals and chords keep the reference page; a voice type doesn't use it.
        $this->assertStringNotContainsString('s-ref-body', $html);
    }

    public function test_the_ladder_puts_every_type_on_one_scale_from_e2_to_a5(): void
    {
        $vt = VoiceTypePage::of('alto', [], true);

        $this->assertSame(['E2', 'C3', 'C4', 'C5', 'A5'], array_column($vt['ticks'], 'name'));
        $this->assertSame(0.0, $vt['ticks'][0]['left']);
        $this->assertSame(100.0, $vt['ticks'][4]['left']);
        $alto = $vt['ladder'][3];
        $this->assertSame(['Alto', 'F3 to D5', true], [$alto['name'], $alto['range'], $alto['current']]);
        $this->assertEqualsWithDelta((53 - 40) / 41 * 100, $alto['left'], 0.01);
        $this->assertEqualsWithDelta((74 - 53) / 41 * 100, $alto['width'], 0.02);
        $this->assertSame('Alto parts to start with', $vt['partsHeading']);
        $this->assertSame('Song parts to start with', VoiceTypePage::of('baritone', [], true)['partsHeading'], 'a baritone sings two parts');
    }

    public function test_a_song_part_is_a_bar_inside_the_type_range(): void
    {
        $song = ['title' => 'A Song', 'name' => 'Alto', 'url' => '/songs/a-song/alto', 'low' => 57, 'high' => 67, 'lowName' => 'A3', 'highName' => 'G4'];
        $bar = VoiceTypePage::of('alto', [$song], false)['songs'][0];

        $this->assertSame('A3 to G4', $bar['range']);
        $this->assertEqualsWithDelta((57 - 53) / 21 * 100, $bar['left'], 0.01);
        $this->assertEqualsWithDelta((67 - 57) / 21 * 100, $bar['width'], 0.02);
        $this->assertNotContains('#faq', array_column(VoiceTypePage::of('alto', [$song], false)['contents'], 0), 'no questions, no link to them');
    }

    public function test_each_type_names_as_neighbours_the_types_its_guide_tells_it_from(): void
    {
        $this->assertSame(array_keys(Reference::VOICE_TYPES), array_keys(VoiceTypePage::NEIGHBOURS));
        foreach (VoiceTypePage::NEIGHBOURS as $slug => $neighbours) {
            $heading = strtolower((string) (Reference::page('voice-types', $slug)['guide'][1]['heading'] ?? ''));
            foreach ($neighbours as $near) {
                $this->assertStringContainsString(strtolower((string) Reference::page('voice-types', $near)['name']), $heading, "{$slug}: {$near}");
            }
        }
    }
}
