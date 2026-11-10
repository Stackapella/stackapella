<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\CourseCss;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A page's course stylesheet on the real pages and the real css/courses.css (docs/css-split-plan.md, step 1): the layout
 * links /course-css/<the courses it shows>.css at courses.css's hash, the route answers only canonical keys of known
 * courses, kept a year only at this build's hash, and every course a page shows has its rules in its sheet.
 */
final class CourseCssPagesTest extends TestCase
{
    // The page requests write rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    private string $web;

    private string $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->web = (string) config('app.web_path');
        $this->version = CourseCss::version($this->web);
    }

    /** The course sheet a page links, from its HTML. */
    private function linked(string $html): ?string
    {
        return preg_match('#<link rel="stylesheet" href="(/course-css/[a-z0-9.]+\.css\?v=[0-9a-f]{12})">#', $html, $m) === 1 ? $m[1] : null;
    }

    public function test_every_page_showing_a_course_links_a_sheet_with_its_courses_rules(): void
    {
        $whole = (string) file_get_contents($this->web.CourseCss::WHOLE);
        $paths = [
            ...array_map(static fn (string $slug): string => '/courses/'.$slug, SitePages::courses()),
            ...array_map(static fn (string $slug): string => '/features/'.$slug, SitePages::FEATURES),
            ...array_map(static fn (string $slug): string => '/for/'.$slug, SitePages::FOR_PAGES),
        ];
        $checked = 0;

        foreach ($paths as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $shown = CourseCss::shown($html);

            if ($shown === []) {
                $this->assertNull($this->linked($html), "{$path}: no course, no course sheet");

                continue;
            }

            $url = $this->linked($html);
            $this->assertNotNull($url, "{$path}: links its course sheet");
            $this->assertStringNotContainsString('href="/css/courses.css', $html, "{$path}: not the whole file");
            $sheet = (string) $this->get((string) $url)->assertOk()->getContent();

            foreach ($shown as $prefix) {
                // A course with rules of its own in courses.css has them in the page's sheet.
                if (preg_match('/\.cp-'.$prefix.'(?![a-z0-9])/', $whole) === 1) {
                    $this->assertMatchesRegularExpression('/\.cp-'.$prefix.'(?![a-z0-9])/', $sheet, "{$path}: .cp-{$prefix}'s rules are in its sheet");
                }
            }
            $this->assertLessThan(strlen($whole) / 2, strlen($sheet), "{$path}: its sheet is a part of courses.css");
            $checked++;
        }

        $this->assertGreaterThan(10, $checked, 'the course pages were checked');
    }

    public function test_the_route_answers_only_canonical_keys_of_known_courses(): void
    {
        $known = CourseCss::known($this->web);
        $this->assertGreaterThan(10, count($known));
        [$a, $b] = [$known[0], $known[1]];

        $ok = $this->get("/course-css/{$a}.{$b}.css?v={$this->version}")->assertOk();
        $this->assertSame('text/css; charset=utf-8', $ok->headers->get('Content-Type'));
        $this->assertSame('immutable, max-age=31536000, public', $ok->headers->get('Cache-Control'));
        $this->assertSame('public, max-age=31536000', $ok->headers->get('Cloudflare-CDN-Cache-Control'));
        $this->assertNull($ok->headers->get('Set-Cookie'));
        $etag = (string) $ok->headers->get('ETag');
        $this->assertNotSame('', $etag);
        // Warm: built once per courses.css and set of courses.
        $this->assertTrue(Cache::has('course-css:'.$this->version.':'.$a.'.'.$b));

        // The same sheet asked again with its ETag: not modified.
        $this->get("/course-css/{$a}.{$b}.css?v={$this->version}", ['If-None-Match' => $etag])->assertStatus(304);
        // Another order, or a course twice: the canonical address, query kept.
        $this->get("/course-css/{$b}.{$a}.css?v={$this->version}")->assertStatus(301)->assertRedirect("/course-css/{$a}.{$b}.css?v={$this->version}");
        $this->get("/course-css/{$a}.{$a}.css")->assertStatus(301)->assertRedirect("/course-css/{$a}.css");
        // A course with no rules, or none at all: no sheet, so no new cache keys.
        $this->get('/course-css/zzz9.css')->assertNotFound();
        $this->get("/course-css/{$a}.nope.css")->assertNotFound();
        $this->get('/course-css/'.implode('.', array_slice($known, 0, 13)).'.css')->assertNotFound();
        // A page with course classes but no course of its own gets the rules every page keeps.
        $this->get('/course-css/'.CourseCss::BASE.'.css?v='.$this->version)->assertOk();

        // A stale or missing v revalidates: never a year.
        foreach (["/course-css/{$a}.css?v=000000000000", "/course-css/{$a}.css"] as $stale) {
            $response = $this->get($stale)->assertOk();
            $this->assertSame('max-age=0, must-revalidate, no-cache, private', $response->headers->get('Cache-Control'), $stale);
            $this->assertSame('no-store', $response->headers->get('Cloudflare-CDN-Cache-Control'), $stale);
        }
    }

    public function test_the_layout_falls_back_to_the_whole_file_when_the_sheet_fails(): void
    {
        $page = '<main class="cp-aa">x</main>';
        $this->assertSame(CourseCss::WHOLE, CourseCss::url('/nowhere', $page), 'courses.css missing: linked plainly');
        $this->assertNull(CourseCss::url($this->web, '<main class="s-lede">x</main>'));
        $this->assertStringStartsWith('/course-css/', (string) CourseCss::url($this->web, $page));
    }
}
