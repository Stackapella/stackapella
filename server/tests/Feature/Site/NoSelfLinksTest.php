<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * No page's own content links back to the page it's on (Hillel, 2026-11-23: "For directors" on For choir directors went
 * back to its top). The header and footer may mark the current page; the main content never offers it as a next step.
 */
final class NoSelfLinksTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return array_combine($paths = ['/', '/pricing', '/for/choir-directors', '/features', '/features/choirs', '/how-it-works', '/about', '/press', '/tools', '/courses', '/songs', '/help', '/integrations'], array_map(static fn (string $p): array => [$p], $paths));
    }

    #[DataProvider('pages')]
    public function test_the_main_content_never_links_to_its_own_page(string $path): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $html = (string) $this->get($path)->assertOk()->getContent();
        $main = preg_match('#<main\b.*?</main>#s', $html, $m) === 1 ? $m[0] : $html;

        preg_match_all('#href="([^"]*)"#', $main, $links);
        foreach ($links[1] as $href) {
            $target = html_entity_decode((string) parse_url($href, PHP_URL_PATH), ENT_QUOTES);
            $host = parse_url($href, PHP_URL_HOST);
            // Another host, an anchor, or a query that changes something (a currency, a song in the app) isn't a way back.
            if ($host !== null || $target === '' || str_starts_with($href, '#') || parse_url($href, PHP_URL_QUERY) !== null) {
                continue;
            }
            $this->assertNotSame(rtrim($path, '/'), rtrim($target, '/'), $path.' links to itself: '.$href);
        }
    }
}
