<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Http\Hosts;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The free tools (docs/tools-plan.md): the hub lists and links every built tool, and each tool's page has
 * its H1, how to use it, its questions, the tool itself and one way into the app tagged ?ref=tool-<slug>.
 */
final class ToolPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hub_lists_and_links_every_built_tool(): void
    {
        $this->assertNotSame([], SitePages::TOOLS);
        $hub = $this->get('/tools')->assertOk()->assertSee(__('site.tools.index.headline'));

        foreach (SitePages::TOOLS as $slug) {
            $hub->assertSee('href="/tools/'.$slug.'"', false)->assertSee(__('site.tools.pages.'.$slug.'.name'));
        }
    }

    public function test_each_tool_has_its_headline_how_questions_tool_and_way_into_the_app(): void
    {
        foreach (SitePages::TOOLS as $slug) {
            $words = (array) Lang::get('site.tools.pages.'.$slug);
            $this->assertSame((string) $words['title'].' | '.__('meta.brand'), SitePages::title((string) $words['title']), "{$slug}'s title is too long to carry the name in search");
            $html = (string) $this->get('/tools/'.$slug)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$slug} has one h1");
            $this->assertStringContainsString('>'.e((string) $words['headline']).'</h1>', $html);
            foreach ((array) $words['how'] as $line) {
                $this->assertStringContainsString(e((string) $line), $html);
            }
            foreach ((array) $words['faq'] as [$question, $answer]) {
                $this->assertStringContainsString(e((string) $question), $html);
                $this->assertStringContainsString(e((string) $answer), $html);
            }
            $to = (string) $words['try_to'];
            $try = Hosts::app($to.(str_contains($to, '?') ? '&' : '?').'ref=tool-'.$slug);
            $this->assertStringContainsString('href="'.e($try).'"', $html, "{$slug}'s way into the app carries its ref");
            $this->assertStringContainsString('data-tool="'.$slug.'"', $html);
            $this->assertStringContainsString('data-mount="'.SitePages::TOOL_MOUNTS[$slug].'"', $html);
        }
    }

    public function test_a_tool_with_a_free_lesson_carries_its_line_and_its_tagged_link_for_after_a_result(): void
    {
        $with = 0;
        foreach (SitePages::TOOLS as $slug) {
            $lesson = Lang::get('site.tools.pages.'.$slug.'.lesson');
            $html = (string) $this->get('/tools/'.$slug)->assertOk()->getContent();
            if (! is_array($lesson)) {
                $this->assertStringNotContainsString('data-tool-lesson', $html, "{$slug} has no lesson line");

                continue;
            }
            $with++;
            $this->assertMatchesRegularExpression('#^/learn/[\w-]+$#', (string) $lesson['to'], "{$slug}'s lesson is a lesson");
            preg_match('#<script type="application/json" data-tool-lesson>(.*?)</script>#s', $html, $found);
            $this->assertNotEmpty($found, "{$slug} carries its lesson line");
            $this->assertSame([
                'line' => $lesson['line'],
                'link' => $lesson['link'],
                'to' => Hosts::app($lesson['to'].'?ref=tool-'.$slug),
            ], json_decode(html_entity_decode($found[1]), true), "{$slug}'s lesson line and its tagged link");
        }
        $this->assertGreaterThanOrEqual(7, $with, 'the tuner, pitch matching, drone, intonation, both ear trainers and the range test');
    }

    public function test_the_synth_tools_carry_their_guide_a_reference_table_and_a_neighbouring_tool(): void
    {
        foreach (array_intersect(['pitch-pipe', 'tuning-forks', 'drone'], SitePages::TOOLS) as $slug) {
            $words = (array) Lang::get('site.tools.pages.'.$slug);
            $html = (string) $this->get('/tools/'.$slug)->getContent();

            $this->assertGreaterThanOrEqual(8, count((array) $words['faq']), "{$slug} answers the questions people ask");
            foreach ((array) $words['guide'] as $section) {
                $this->assertStringContainsString(e((string) $section['heading']).'</h2>', $html);
            }
            $this->assertSame(1, substr_count($html, '<table'), "{$slug} has one reference table");
            [$line, $other] = $words['related'];
            $this->assertContains($other, SitePages::TOOLS);
            // The neighbouring tool is the featured card in More free tools.
            $this->assertStringContainsString('<a href="/tools/'.$other.'" class="s-tfeat', $html);
            $this->assertStringContainsString('<b>'.e((string) $line).'</b>', $html);
        }
    }

    public function test_open_the_app_is_tagged_for_a_visitor_from_search_and_keeps_a_ref_they_brought(): void
    {
        // The header's Open the app, not the logo (which links the landing with the page's own tag).
        $app = static fn (string $ref): string => 'href="'.e(Hosts::app('/?ref='.$ref)).'" class="btn-secondary"';
        $slug = SitePages::TOOLS[0];

        $this->assertStringContainsString($app('tool-'.$slug), (string) $this->get('/tools/'.$slug)->getContent());
        $this->assertStringContainsString($app('tools-embed'), (string) $this->get('/tools/embed')->getContent());
        if (SitePages::hubLive()) {
            $this->assertStringContainsString($app('tools'), (string) $this->get('/tools')->getContent());
        }
        foreach (array_slice(SitePages::variants(), 0, 1) as $variant) {
            $this->assertStringContainsString($app('tool-'.$variant), (string) $this->get('/tools/'.$variant)->getContent());
        }

        $html = (string) $this->get('/tools/'.$slug.'?ref=warmups')->getContent();
        $this->assertStringContainsString($app('warmups'), $html);
        $this->assertStringNotContainsString($app('tool-'.$slug), $html);
    }

    public function test_an_unknown_tool_is_not_found(): void
    {
        $this->get('/tools/kazoo')->assertNotFound();
    }

    public function test_the_sitemap_lists_the_hub_and_every_tool(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
        $base = rtrim((string) config('app.url'), '/');

        $this->assertStringContainsString('<loc>'.$base.'/tools</loc>', $xml);
        foreach (SitePages::TOOLS as $slug) {
            $this->assertStringContainsString('<loc>'.$base.'/tools/'.$slug.'</loc>', $xml);
        }
    }
}
