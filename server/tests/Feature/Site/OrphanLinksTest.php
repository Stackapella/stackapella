<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pages the internal-link audit found with no link to them (2026-11-13) each have one now: the copyright (DMCA)
 * policy in the footer (four a column, Hillel's rule), every policy at the foot of the others (security too) (refunds, the coach agreement and the
 * marketplace terms from /legal/terms too), the embeds from /tools, and the comparisons from /features while they're
 * listed. The whole-site check is the site_links flow (web/tests/flows/site_links.py).
 */
final class OrphanLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_the_footer_links_the_copyright_policy(): void
    {
        $this->get('/')->assertOk()->assertSee('href="/legal/copyright"', false);
    }

    public function test_each_policy_lists_the_others_and_never_itself(): void
    {
        $terms = (string) $this->get('/legal/terms')->assertOk()->getContent();
        $at = (int) strpos($terms, 'id="others-h"');
        $others = substr($terms, $at, (int) strpos($terms, '</nav>', $at) - $at);
        foreach (['refunds', 'coach-agreement', 'marketplace-terms', 'copyright', 'security', 'community-guidelines', 'privacy'] as $page) {
            $this->assertStringContainsString('href="/legal/'.$page.'"', $others, "/legal/terms lists {$page}");
        }
        $this->assertStringNotContainsString('href="/legal/terms"', $others, 'a policy never lists itself');
    }

    public function test_tools_links_the_embeds_and_features_links_the_comparisons_while_listed(): void
    {
        $this->get('/tools')->assertOk()->assertSee('href="/tools/embed"', false);

        $this->get('/features')->assertOk()->assertDontSee('href="/compare"', false);
        app(Flags::class)->set('compare_pages', 'on', null, 'Listing the comparisons');
        $this->get('/features')->assertOk()->assertSee('href="/compare"', false);
    }
}
