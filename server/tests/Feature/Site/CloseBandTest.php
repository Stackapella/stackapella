<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\Explore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The five marketing pages end the same way (docs/marketing-pages-cohesion-plan.md, piece 6; Hillel, 2026-11-14): one
 * closing band with Start singing to lesson 1 and "Explore Stackapella", the other four pages, never the page itself,
 * each with its fact and its number.
 */
final class CloseBandTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_every_one_of_the_five_pages_ends_in_the_one_band_leading_to_the_other_four(): void
    {
        // (The home page, since 2026-11-16, ends in its own finale: the mark, Start singing and the four ways on.)
        $paths = ['songs' => '/songs', 'courses' => '/courses', 'tools' => '/tools', 'pricing' => '/pricing'];
        foreach ($paths as $page => $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'class="s-ink s-close"'), "{$path}: one closing band");
            $band = substr($html, (int) strpos($html, 'class="s-ink s-close"'));
            $band = substr($band, 0, (int) strpos($band, '</section>'));
            $this->assertStringContainsString('/learn/1-comfortable', $band, "{$path}: Start singing goes to lesson 1");
            $this->assertStringContainsString(e(__('pages.site.close.'.$page.'.em')), $band, "{$path}: its own line");
            preg_match_all('#class="s-explore-way s-hub-([a-z]+)"#', $band, $ways);
            $this->assertSame(array_values(array_diff(Explore::PAGES, [$page])), $ways[1], "{$path}: the other four, in order");
        }
    }

    public function test_each_way_says_its_number(): void
    {
        $counts = Explore::counts();
        $this->assertGreaterThan(0, $counts['songs']);
        $this->assertGreaterThan(0, $counts['courses']);
        $this->assertGreaterThan(0, $counts['tools']);
        $ways = collect(Explore::ways('home'))->keyBy('key');
        $this->assertSame((string) $counts['songs'], $ways['songs']['n']);
        $this->assertStringContainsString('songs to learn', $ways['songs']['after']);
        $this->assertNull($ways['pricing']['n'], 'pricing has no number, so its readout is a word');
        $this->assertSame('Free', $ways['pricing']['readout']);
        $this->assertSame('4', collect(Explore::ways('songs'))->keyBy('key')['home']['n'], 'the landing: its four voices');
    }

    public function test_off_the_nexus_there_is_no_landing_or_pricing_to_lead_to(): void
    {
        config(['app.is_nexus' => false]);
        $this->assertSame(['courses', 'tools'], array_column(Explore::ways('songs'), 'key'));
    }
}
