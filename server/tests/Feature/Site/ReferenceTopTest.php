<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reference page's top (Hillel's part page redesign, 2026-11-10; Reference::top): the pages to switch to as chips, all
 * six voice types but only an interval's or a chord's neighbours, this one current.
 */
final class ReferenceTopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_an_interval_shows_its_neighbours(): void
    {
        $html = (string) $this->get('/intervals/perfect-fifth')->assertOk()->getContent();

        $this->assertStringContainsString('<span class="s-spart-chip" aria-current="page"><b>Perfect fifth</b></span>', $html);
        $this->assertSame(2, substr_count($html, '" class="s-spart-chip">'));
        $this->assertStringContainsString(e(__('site.reference.intervals.index.switch')), $html);
        // The whole family is still one tap away below.
        $this->assertStringContainsString('id="others-h"', $html);
    }

    public function test_the_first_chord_has_one_neighbour_and_a_voice_type_shows_all_six(): void
    {
        $this->assertSame(1, substr_count((string) $this->get('/chords/major')->getContent(), '" class="s-spart-chip">'));

        $html = (string) $this->get('/voice-types/alto')->assertOk()->getContent();
        $this->assertSame(5, substr_count($html, '" class="s-spart-chip">'));
        // The chips show the family, so its cards below don't repeat it.
        $this->assertStringNotContainsString('id="others-h"', $html);
    }
}
