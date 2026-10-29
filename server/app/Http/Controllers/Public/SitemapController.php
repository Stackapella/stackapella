<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Choirs\Choirs;
use App\Actions\Ideas\Ideas;
use App\Http\Controllers\Controller;
use App\Models\Choir;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Site\HelpGuide;
use App\Support\Site\OpenSource;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /sitemap.xml and /robots.txt (docs/go-to-market-plan.md, "Search"): the home, the songs, the
 * guides and the legal pages that exist. Shared harmonies are never in it (they're unlisted).
 */
final class SitemapController extends Controller
{
    /** Ideas in the sitemap, newest first, leaving room under its 50,000 for everything else. */
    public const MOST_IDEAS = 45000;

    public function sitemap(Ideas $ideas, Choirs $choirs): Response
    {
        $base = Hosts::publicUrl();
        $urls = [$base.'/', $base.'/songs', $base.'/pricing', $base.'/help', ...array_map(static fn (string $slug): string => $base.'/help/'.$slug, HelpGuide::slugs())];

        // The public site's own pages (docs/public-site-plan.md), coming-soon ones too.
        foreach (SitePages::paths() as $path) {
            $urls[] = $base.$path;
        }

        foreach (Template::query()->listed()->whereNotNull('current_version_id')->orderBy('slug')->pluck('slug') as $slug) {
            $urls[] = $base.'/songs/'.$slug;
        }

        // Ideas on the shelf and the choirs people have sung in, while each is open.
        // One sitemap holds at most 50,000 addresses: the newest ideas fill what's left.
        if ($ideas->isOpen()) {
            foreach ($ideas->listed()->orderByDesc('id')->limit(self::MOST_IDEAS)->pluck('public_id') as $id) {
                $urls[] = $base.'/ideas/'.$id;
            }
        }

        if ($choirs->isOpen()) {
            $slugs = Template::query()->listed()->whereNull('deleted_at')->whereIn('id', Choir::query()->whereHas('voices', fn ($voices) => $voices->whereNotNull('in_mix_at'))->select('template_id'))->orderBy('slug')->pluck('slug');

            foreach ($slugs as $slug) {
                $urls[] = $base.'/choirs/'.$slug;
            }
        }

        foreach (DocumentPagesController::guides() as $guide) {
            $urls[] = $base.'/guides/'.$guide;
        }

        // Open source's pages, once the flag is on (docs/open-source-plan.md, 5.1).
        $legal = DocumentPagesController::LEGAL;
        if (OpenSource::on()) {
            $urls = [...$urls, ...array_map(static fn (string $path): string => $base.$path, OpenSource::PAGES)];
            $legal = [...$legal, ...DocumentPagesController::OPEN_SOURCE_LEGAL];
        }

        foreach ($legal as $page) {
            if (is_file(resource_path("legal/en/{$page}.md"))) {
                $urls[] = $base.'/legal/'.$page;
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", array_map(static fn (string $url): string => '  <url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>', $urls))
            ."\n</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * The link-preview crawlers (a share's card on Facebook, WhatsApp, X, LinkedIn, Slack, Discord,
     * Reddit, Telegram, Bluesky, Pinterest): a shared harmony's page is made to be previewed there,
     * so they may read /h/ and /embed/. Search engines may not; those pages say noindex too.
     */
    private static function previewBots(string $rules): string
    {
        $bots = ['facebookexternalhit', 'Facebot', 'meta-externalagent', 'Twitterbot', 'LinkedInBot', 'Slackbot', 'Slackbot-LinkExpanding', 'WhatsApp', 'Discordbot', 'redditbot', 'TelegramBot', 'Cardyb', 'Pinterestbot'];

        return implode('', array_map(static fn (string $bot): string => "User-agent: {$bot}\n", $bots)).$rules;
    }

    public function robots(Request $request): Response
    {
        $base = Hosts::publicUrl();
        // Before launch (and on the staging beta) nothing is indexed, and the app's host never is.
        // Never on the app's host or the public site's old one (its pages have moved).
        $indexed = config('ops.indexing') === true && ! (Hosts::split() && (Hosts::onAppHost($request) || Hosts::onOldPublicHost($request)));
        $body = $indexed
            ? "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /h/\nDisallow: /embed/\nDisallow: /notice\n\n".self::previewBots("Allow: /h/\nAllow: /embed/\nDisallow: /admin\nDisallow: /api/\nDisallow: /notice\n")."\nSitemap: {$base}/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n".(config('ops.indexing') === true && Hosts::split() && Hosts::onOldPublicHost($request)
                // A link shared from the beta host still gets its preview (the page moves on).
                ? "\n".self::previewBots("Allow: /h/\nAllow: /embed/\nDisallow: /\n")
                : '');

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
    }
}
