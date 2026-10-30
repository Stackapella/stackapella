<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Prices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The comparison pages (docs/compare-pages-plan.md) answer either way, but stay out of search and
 * the sitemap, with no footer link, until the compare_pages switch is on. Stand-in words and views
 * here, so the routing is tested apart from the landing agent's pages.
 */
final class ComparePagesTest extends TestCase
{
    use RefreshDatabase;

    private string $views;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ops.indexing' => true]);
        __('site.features.index.title');
        Lang::addLines([
            'site.compare.index' => ['title' => 'Compare', 'description' => 'How it compares.'],
            'site.compare.common' => ['price' => 'Free to start. Plus is :price_month a month'],
            'site.compare.pages' => ['howli' => ['title' => 'Stackapella and Howli', 'description' => 'Side by side.', 'table' => [['Price', ':price_month a month or :price_year a year']]]],
        ], 'en');
        $this->views = storage_path('framework/testing/compare-views');
        File::ensureDirectoryExists($this->views.'/site');
        File::put($this->views.'/site/compare.blade.php', 'hub {{ count($pages) }} {{ $links[\'compare\'] ? \'linked\' : \'unlinked\' }}');
        File::put($this->views.'/site/compare-page.blade.php', 'page {{ $page[\'slug\'] }} {{ $page[\'title\'] }} {{ $page[\'table\'][0][1] }} {{ $common[\'price\'] }}');
        View::getFinder()->prependLocation($this->views);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->views);
        parent::tearDown();
    }

    public function test_the_pages_stay_out_of_search_and_the_sitemap_until_the_switch_is_on(): void
    {
        $this->get('/compare')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('hub 1 unlinked');
        $this->get('/compare/howli')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('page howli Stackapella and Howli');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/compare');

        app(Flags::class)->set('compare_pages', 'on', null, 'Hillel: go');

        $this->get('/compare')->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertSee('hub 1 linked');
        $this->get('/compare/howli')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/compare</loc>', false)->assertSee('/compare/howli</loc>', false);
    }

    public function test_our_prices_are_filled_in_the_visitors_currency(): void
    {
        $usd = Prices::of('usd');
        $this->get('/compare/howli')->assertOk()->assertSee($usd['month'].' a month or '.$usd['year'].' a year')->assertDontSee(':price');
        $gbp = Prices::of('gbp');
        $this->get('/compare/howli?currency=gbp')->assertOk()->assertSee($gbp['month'].' a month or '.$gbp['year'].' a year')->assertSee('Free to start. Plus is '.$gbp['month'].' a month');
    }

    public function test_an_unknown_page_is_gone(): void
    {
        $this->get('/compare/no-such-app')->assertNotFound();
    }
}
