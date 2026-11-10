<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Site\SitemapPaths;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /sitemap.xml and /robots.txt (docs/go-to-market-plan.md, "Search"): the pages SitemapPaths lists,
 * each with its lastmod when it has an honest one. Shared harmonies are never in it (unlisted).
 */
final class SitemapController extends Controller
{
    public function sitemap(SitemapPaths $pages): Response
    {
        $base = Hosts::publicUrl();
        $dates = $pages->lastModified();
        $url = static function (string $path) use ($base, $dates): string {
            $lastmod = isset($dates[$path]) ? '<lastmod>'.$dates[$path].'</lastmod>' : '';

            return '  <url><loc>'.htmlspecialchars($base.$path, ENT_XML1).'</loc>'.$lastmod.'</url>';
        };

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", array_map($url, $pages->all()))
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
            // /cdn-cgi/ is Cloudflare's (its email protection rewrites the footer's address into a
            // link there that answers 404 to a crawler), never a page of ours.
            ? "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /h/\nDisallow: /embed/\nDisallow: /notice\nDisallow: /cdn-cgi/\n\n".self::previewBots("Allow: /h/\nAllow: /embed/\nDisallow: /admin\nDisallow: /api/\nDisallow: /notice\n")."\nSitemap: {$base}/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n".(config('ops.indexing') === true && Hosts::split() && Hosts::onOldPublicHost($request)
                // A link shared from the beta host still gets its preview (the page moves on).
                ? "\n".self::previewBots("Allow: /h/\nAllow: /embed/\nDisallow: /\n")
                : '');

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
    }
}
