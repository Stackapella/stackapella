<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\Carols;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The Santa hat on the logo (Hillel, 2026-11-10: "only show the hat on the carols page and in the app and marketing on
 * christmas", "also add eve"): on the carols page from 1 November through Christmas Day, from the server; on every other
 * page it's there hidden, for js/public/season.js to show on the visitor's own 24 and 25 December (web/tests/
 * season.test.js proves those days); after Christmas, nowhere.
 */
final class SantaHatTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const SHOWN = '<svg class="s-hat" width="39" height="42" viewBox="-9 -12 39 42" aria-hidden="true" focusable="false">';

    private const WAITING = '<svg class="s-hat" width="39" height="42" viewBox="-9 -12 39 42" aria-hidden="true" focusable="false" hidden data-hat-christmas>';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
    }

    public function test_the_carols_page_wears_it_all_season_and_every_other_page_waits_for_christmas(): void
    {
        foreach (['2026-11-10 12:00:00', '2026-12-24 12:00:00', '2026-12-25 12:00:00'] as $day) {
            $this->travelTo($day);
            $this->get(Carols::PATH)->assertOk()->assertSee(self::SHOWN, false);
            // The landing has it hidden: the visitor's own calendar shows it on the 24th and 25th (season.js).
            $this->get('/')->assertOk()->assertSee(self::WAITING, false)->assertDontSee(self::SHOWN, false);
        }
    }

    public function test_after_christmas_no_page_wears_it_from_the_server(): void
    {
        foreach (['2026-12-26 08:00:00', '2027-02-01 12:00:00', '2027-10-31 12:00:00'] as $day) {
            $this->travelTo($day);
            foreach ([Carols::PATH, '/', '/courses'] as $page) {
                $this->get($page)->assertOk()->assertDontSee(self::SHOWN, false)->assertSee(self::WAITING, false);
            }
        }
    }

    public function test_the_page_loads_the_rule_that_shows_it(): void
    {
        $this->get('/')->assertOk()->assertSee('/js/public/site.js', false);
        $this->assertStringContainsString("import { showHats } from './season.js';", (string) file_get_contents(dirname(base_path()).'/web/js/public/site.js'));
    }
}
