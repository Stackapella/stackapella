<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every public page's load, counted by its section only (sv_<section>): the footer carries the kind while
 * counts are on, never on the app's host, and counts.create keeps it with no source.
 */
final class SiteVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_path_falls_in_one_section(): void
    {
        $paths = [
            '/' => 'home', 'courses' => 'courses', 'courses/vibrato' => 'course', 'courses/harmony' => 'course', 'courses/vibrato/what-vibrato-is' => 'lesson',
            'tools' => 'tools', 'tools/tuner' => 'tools', 'tools/embed' => 'tools', 'songs' => 'songs', 'songs/amazing-grace' => 'songs', 'songs/amazing-grace/alto' => 'part', 'choirs/amazing-grace' => 'songs',
            'guides' => 'guides', 'guides/how-to-learn-a-harmony-part' => 'guides', 'pricing' => 'other', 'intervals/perfect-fifth' => 'other', 'courses-like' => 'other',
        ];

        foreach ($paths as $path => $section) {
            $this->assertSame($section, SiteVisit::section($path), $path);
        }

        foreach (array_unique(array_values($paths)) as $section) {
            $this->assertContains('sv_'.$section, (array) config('counts.kinds'));
        }
    }

    public function test_a_public_page_carries_its_section_only_while_counts_are_on(): void
    {
        config(['counts.enabled' => true]);
        foreach (['/courses' => 'sv_courses', '/courses/vibrato' => 'sv_course', '/tools/tuner' => 'sv_tools', '/songs' => 'sv_songs'] as $path => $kind) {
            $this->get($path)->assertOk()->assertSee('<script type="application/json" data-site-visit>{"kind":"'.$kind.'"}</script>', false);
        }

        config(['counts.enabled' => false]);
        $this->get('/courses')->assertOk()->assertDontSee('data-site-visit', false);
    }

    public function test_never_on_the_apps_host_when_the_two_are_apart(): void
    {
        config(['counts.enabled' => true, 'app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://site.stackapella.test']);

        $this->assertSame('sv_tools', SiteVisit::kind(Request::create('https://site.stackapella.test/tools/tuner')));
        $this->assertNull(SiteVisit::kind(Request::create('https://app.stackapella.test/tools/tuner')));
    }

    public function test_the_count_is_kept_by_section_with_no_source(): void
    {
        config(['counts.enabled' => true]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'sv_courses'], ['kind' => 'sv_lesson'], ['kind' => 'part_play'], ['kind' => 'sv_nowhere']], 'source' => 'reddit'])->assertNoContent();

        $rows = DB::table('usage_counts')->orderBy('kind')->get(['kind', 'source', 'count'])->map(fn (object $r): array => [(string) $r->kind, (string) $r->source, (int) $r->count])->all();
        $this->assertSame([['part_play', '', 1], ['sv_courses', '', 1], ['sv_lesson', '', 1]], $rows);
    }
}
