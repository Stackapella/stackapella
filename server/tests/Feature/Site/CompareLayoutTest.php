<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A comparison in the width design (docs/design-rules.md, "A page about one thing uses the width"): the lead beside the
 * heading, the questions and the sources as rows with their heading at left, and Start singing as its own pill in the
 * dark close.
 */
final class CompareLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_comparison_puts_its_lead_beside_its_heading_and_its_questions_and_sources_in_rows(): void
    {
        $html = (string) $this->get('/compare/choirmate')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<section class="s-wrap s-featurehead cmp-head cmp-top".*?<h1 id="page-h">.*?</div>\s*<p class="s-lede">#s', $html);
        $this->assertMatchesRegularExpression('#<section class="s-wrap s-sec cmp-row" aria-labelledby="faq-h">\s*<h2 id="faq-h"#', $html);
        $this->assertStringContainsString('class="s-wrap s-sec cmp-sources cmp-row"', $html);
        $this->assertMatchesRegularExpression('#<section class="s-ink cmp-try".*?<div class="s-wrap cmp-try-in">.*?</div>\s*<a href="[^"]+" class="btn-primary btn-lg">#s', $html);
    }

    public function test_the_hub_puts_its_lead_beside_its_heading(): void
    {
        $html = (string) $this->get('/compare')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<section class="s-wrap s-featurehead cmp-head cmp-top".*?<h1 id="page-h">.*?</div>\s*<p class="s-lede">#s', $html);
    }
}
