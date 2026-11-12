<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SiteCss;
use App\Support\Site\SitemapPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A page family's stylesheets on the real pages, the real sheets and the real manifest (docs/css-split-plan.md, step 4):
 * every sitemap page links its family's sheets, never the whole site.css for want of a class in the manifest; the route
 * answers only known families, kept a year only at this build's version; and a page showing a class its family can't
 * gets the whole sheets.
 */
final class SiteCssPagesTest extends TestCase
{
    // The page requests write rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    /** Pages of one shape (/songs/<song>/<part>) read at most, so the test reads each kind of page without reading them all. */
    private const PER_SHAPE = 4;

    private string $web;

    private string $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->web = (string) config('app.web_path');
        $this->version = SiteCss::version($this->web);
    }

    /** @return array{0: string|null, 1: string|null} the page's stylesheet link and its game sheet, from its HTML */
    private function linked(string $html): array
    {
        return [
            preg_match('#<link rel="stylesheet" href="(/(?:site-css/[a-z]+|css/site)\.css(?:\?v=[0-9a-f]{12})?)">#', $html, $m) === 1 ? $m[1] : null,
            preg_match('#<meta name="game-css" content="([^"]+)">#', $html, $g) === 1 ? $g[1] : null,
        ];
    }

    public function test_every_kind_of_sitemap_page_links_its_familys_sheets(): void
    {
        $this->assertNotSame('', $this->version, 'css/site.css and css/site-game.css are built');
        $shapes = [];
        $missing = [];
        $checked = 0;

        foreach (app(SitemapPaths::class)->all() as $url) {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
            $shape = preg_replace('#/[^/]+$#', '/*', $path);

            if (($shapes[$shape] = ($shapes[$shape] ?? 0) + 1) > self::PER_SHAPE) {
                continue;
            }

            foreach ([$path, '/es'.rtrim($path, '/')] as $page) {
                $response = $this->get($page);

                if ($page !== $path && $response->getStatusCode() !== 200) {
                    continue; // a page not (yet) in Spanish
                }

                $html = (string) $response->assertOk()->getContent();
                $family = SiteCss::family($page);
                [$sheet, $game] = $this->linked($html);
                $lacks = SiteCss::missing($this->web, $family, $html);

                if ($lacks !== []) {
                    $missing[] = "{$page} ({$family}): ".implode(' ', array_slice($lacks, 0, 8));

                    continue;
                }

                $this->assertSame("/site-css/{$family}.css?v={$this->version}", $sheet, "{$page}: its family's sheet");
                $this->assertSame("/site-css/{$family}-game.css?v={$this->version}", $game, "{$page}: its family's game sheet");
                $checked++;
            }
        }

        $this->assertSame([], $missing, "These pages show classes their family's manifest lacks, so they get the whole site.css. Write the manifest again from a site serving this checkout (docs/css-split-plan.md, step 4):\n    cd web && python3 -I tool/site_families.py http://127.0.0.1:8000\n".implode("\n", $missing));
        $this->assertGreaterThan(20, $checked);
    }

    public function test_a_page_showing_a_class_its_family_lacks_gets_the_whole_sheets(): void
    {
        // A class a rule needs that no songs page, the chrome or the public JS shows.
        $known = SiteCss::known($this->web);
        $songs = SiteCss::manifest()['songs'] ?? [];
        $lacking = array_values(array_filter(array_map('strval', array_keys($known['styled'])), static fn (string $class): bool => ! isset($songs[$class]) && ! isset($known['words'][$class])));
        $this->assertNotSame([], $lacking);

        $this->assertSame(['/css/site.css?v=', '/css/site-game.css?v='], array_map(
            static fn (string $url): string => (string) preg_replace('/[0-9a-f]{12}$/', '', $url),
            SiteCss::urls($this->web, '/songs', '<main class="'.htmlspecialchars($lacking[0]).'"></main>'),
        ));
        // One no rule needs changes nothing, whatever sheet it gets.
        $this->assertSame("/site-css/songs.css?v={$this->version}", SiteCss::urls($this->web, '/songs', '<main class="flex s-no-such-class-ever"></main>')[0]);
    }

    public function test_the_route_answers_known_families_kept_a_year_at_this_version(): void
    {
        $whole = (string) file_get_contents($this->web.SiteCss::WHOLE);
        $songs = $this->get("/site-css/songs.css?v={$this->version}")->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $this->assertStringContainsString('immutable', (string) $songs->headers->get('Cache-Control'));
        $sheet = (string) $songs->getContent();
        $this->assertLessThan(strlen($whole) * 0.8, strlen($sheet), 'a family sheet leaves out what no page of it can use');
        $this->assertTrue(Cache::has("site-css:{$this->version}:songs"));
        $this->get("/site-css/songs.css?v={$this->version}", ['If-None-Match' => (string) $songs->headers->get('ETag')])->assertStatus(304);
        $this->get("/site-css/tools-game.css?v={$this->version}")->assertOk();

        foreach (['/site-css/songs.css?v=000000000000', '/site-css/songs.css'] as $stale) {
            $this->assertStringContainsString('no-cache', (string) $this->get($stale)->assertOk()->headers->get('Cache-Control'), $stale);
        }

        $this->get('/site-css/nope.css')->assertNotFound();
        $this->get('/site-css/songs-games.css')->assertNotFound();
    }

    public function test_a_family_sheet_keeps_every_rule_its_pages_classes_need(): void
    {
        // A tools-only class's rules aren't in the songs sheet, and are in the tools one; the songs' own are in theirs.
        $manifest = SiteCss::manifest();
        $known = SiteCss::known($this->web);
        $toolsOnly = array_values(array_filter(array_keys($manifest['tools'] ?? []), static fn (string $class): bool => str_starts_with($class, 's-tool') && ! isset($manifest['songs'][$class]) && ! isset($known['words'][$class])));
        $this->assertNotSame([], $toolsOnly);
        $songs = SiteCss::sheet($this->web, 'songs', false);
        $tools = SiteCss::sheet($this->web, 'tools', false);
        $whole = (string) file_get_contents($this->web.SiteCss::WHOLE);
        $class = null;

        foreach ($toolsOnly as $candidate) {
            if (str_contains($whole, '.'.$candidate.'{')) {
                $class = $candidate;

                break;
            }
        }

        $this->assertNotNull($class, 'a tools-only class with a rule of its own');
        $this->assertStringContainsString('.'.$class.'{', $tools);
        $this->assertStringNotContainsString('.'.$class.'{', $songs);
    }
}
