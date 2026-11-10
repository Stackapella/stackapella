<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Site\SitemapCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Every address the sitemap gives search engines is a real page (Search Console warns on any
 * that isn't), checked by app:sitemap:check's own walk (SitemapCheck): on the public site, answering
 * 200 itself (no redirect, no 404), indexable (no noindex header or meta, allowed by robots.txt) and
 * exactly its own canonical, shared with no other; every page the hubs link to is in it or says
 * noindex. One broken page of each kind is seeded beside the real ones and named as its kind, and
 * nothing else is. Each real page also has a title and description no other page has and JSON-LD
 * that parses. Every course is open (as in production), so the lesson pages are in it too.
 */
final class SitemapHealthTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const APP = 'https://app.stackapella.test';

    private const SITE = 'https://site.stackapella.test';

    public function test_every_sitemap_address_is_indexable_and_each_seeded_break_is_named_as_its_kind(): void
    {
        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false, 'ops.indexing' => true]);
        $this->catalogue();
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();

        $page = static fn (string $head, string $body = ''): array => ['status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => "<!doctype html><html><head>{$head}</head><body>{$body}</body></html>"];
        $self = static fn (string $path): string => '<link rel="canonical" href="'.self::SITE.$path.'">';
        $seeded = [
            '/seeded/redirect' => ['status' => 301, 'headers' => ['location' => self::SITE.'/seeded/elsewhere'], 'body' => ''],
            '/seeded/missing' => ['status' => 404, 'headers' => [], 'body' => ''],
            '/seeded/slash' => $page('<link rel="canonical" href="'.self::SITE.'/seeded/slash/">'),
            '/seeded/http' => $page('<link rel="canonical" href="'.str_replace('https:', 'http:', self::SITE).'/seeded/http">'),
            '/seeded/no-canonical' => $page('<title>No canonical</title>'),
            '/seeded/noindex-meta' => $page($self('/seeded/noindex-meta').'<meta name="robots" content="noindex, follow">'),
            '/seeded/noindex-header' => ['status' => 200, 'headers' => ['x-robots-tag' => 'noindex']] + ['body' => $page($self('/seeded/noindex-header'))['body']],
            '/admin/seeded' => $page($self('/admin/seeded')),
            '/seeded/twin-a' => $page($self('/seeded/twin')),
            '/seeded/twin-b' => $page($self('/seeded/twin')),
            // Linked from the footer, not in the sitemap.
            '/seeded/unlisted' => $page($self('/seeded/unlisted')),
            '/seeded/unlisted-noindex' => $page($self('/seeded/unlisted-noindex').'<meta name="robots" content="noindex">'),
            '/seeded/linked-moved' => ['status' => 302, 'headers' => ['location' => self::SITE.'/'], 'body' => ''],
            '/seeded/linked-gone' => ['status' => 410, 'headers' => [], 'body' => ''],
        ];
        $listed = ['/seeded/redirect', '/seeded/missing', '/seeded/slash', '/seeded/http', '/seeded/no-canonical', '/seeded/noindex-meta', '/seeded/noindex-header', '/admin/seeded', '/seeded/twin-a', '/seeded/twin-b'];
        $footer = '<a href="/seeded/unlisted">a</a><a href="'.self::SITE.'/seeded/unlisted-noindex">b</a><a href="/seeded/linked-moved#top">c</a><a href="/seeded/linked-gone">d</a>'
            .'<a href="/songs?level=easy">filtered</a><a href="https://elsewhere.example.com/page">away</a><a href="mailto:hello@example.com">mail</a><a href="/css/site.css">file</a><a href="/cdn-cgi/l/email-protection#ab12">cloudflare</a>';

        $titles = [];
        $descriptions = [];
        $problems = [];
        $locs = [];
        $fetch = function (string $url) use ($seeded, $listed, $footer, &$titles, &$descriptions, &$problems, &$locs): array {
            $path = (string) substr($url, strlen(self::SITE));
            if (str_starts_with($url, self::SITE) && isset($seeded[$path])) {
                return $seeded[$path];
            }

            $response = $this->get($url);
            $body = (string) $response->getContent();
            // Beyond what a search engine refuses: every real page has its own title and
            // description, and JSON-LD that parses.
            if (str_contains((string) $response->headers->get('Content-Type'), 'text/html') && $response->getStatusCode() === 200) {
                preg_match('#<title>([^<]*)</title>#', $body, $title);
                preg_match('#<meta name="description" content="([^"]*)"#', $body, $description);
                $titles[$title[1] ?? ''][] = $url;
                $descriptions[$description[1] ?? ''][] = $url;
                preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $body, $ld);
                foreach ($ld[1] as $json) {
                    if (! is_array(json_decode($json, true))) {
                        $problems[] = "{$url}: JSON-LD doesn't parse";
                    }
                }
            }
            if ($path === '/sitemap.xml') {
                preg_match_all('#<lastmod>([^<]+)</lastmod>#', $body, $dates);
                foreach ($dates[1] as $date) {
                    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
                }
                $locs = SitemapCheck::locs($body);
                preg_match_all('#<loc>#', $body, $all);
                $this->assertCount(count($locs), $all[0], 'An address is listed twice.');
                $extra = array_map(static fn (string $p): string => '<url><loc>'.self::SITE.$p.'</loc></url>', $listed);
                $body = str_replace('</urlset>', implode("\n", $extra)."\n<url><loc>https://elsewhere.example.com/page</loc></url>\n</urlset>", $body);
            }
            if ($path === '/courses') {
                $body = str_replace('</body>', $footer.'</body>', $body);
            }

            return ['status' => $response->getStatusCode(), 'headers' => array_map(static fn (array $values): string => implode(', ', $values), $response->headers->all()), 'body' => $body];
        };

        $found = array_map(static fn (array $problems): array => array_column($problems, 'url'), (new SitemapCheck($fetch(...)))->run(self::SITE));
        $at = static fn (string ...$paths): array => array_map(static fn (string $p): string => self::SITE.$p, $paths);

        $this->assertSame([
            'not_200' => $at('/seeded/redirect', '/seeded/missing'),
            'off_site' => ['https://elsewhere.example.com/page'],
            'no_canonical' => $at('/seeded/no-canonical'),
            'canonical_elsewhere' => $at('/seeded/slash', '/seeded/http', '/seeded/twin-a', '/seeded/twin-b'),
            'noindex' => $at('/seeded/noindex-meta', '/seeded/noindex-header'),
            'disallowed' => $at('/admin/seeded'),
            'shared_canonical' => $at('/seeded/twin'),
            'linked_missing' => $at('/seeded/unlisted'),
            'linked_redirect' => $at('/seeded/linked-moved'),
            'linked_broken' => $at('/seeded/linked-gone'),
        ], $found);

        $this->assertGreaterThan(50, count($locs));
        $this->assertNotSame([], array_filter($locs, static fn (string $loc): bool => preg_match('#/courses/[a-z0-9-]+/[a-z0-9-]+$#', $loc) === 1), 'The lesson pages are listed.');
        foreach (['title' => $titles, 'description' => $descriptions] as $what => $seen) {
            foreach ($seen as $text => $pages) {
                if ($text === '' || count($pages) > 1) {
                    $problems[] = ($text === '' ? "no {$what}: " : "the same {$what} \"{$text}\": ").implode(', ', $pages);
                }
            }
        }
        $this->assertSame([], $problems);
    }

    public function test_a_lesson_page_names_itself_its_course_and_its_trail_for_search(): void
    {
        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false, 'ops.indexing' => true]);
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();

        foreach (array_keys(Lessons::courses()) as $course) {
            $page = Lessons::pages($course)[0];
            $html = (string) $this->get(self::SITE.$page['url'])->assertOk()->getContent();
            preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $ld);
            $items = array_map(static fn (string $json): array => (array) json_decode($json, true), $ld[1]);
            $types = array_column($items, '@type');

            $this->assertContains('LearningResource', $types, $page['url']);
            $this->assertContains('BreadcrumbList', $types, $page['url']);
            $lesson = $items[array_search('LearningResource', $types, true)];
            $this->assertSame(self::SITE.$page['url'], $lesson['url']);
            $this->assertSame('Course', $lesson['isPartOf']['@type']);
            $this->assertSame(self::SITE.$page['up'], $lesson['isPartOf']['url']);
            $trail = array_column($items[array_search('BreadcrumbList', $types, true)]['itemListElement'], 'item');
            $this->assertSame([self::SITE.'/', self::SITE.'/courses', self::SITE.$page['up'], self::SITE.$page['url']], $trail, $page['url']);
        }
    }
}
