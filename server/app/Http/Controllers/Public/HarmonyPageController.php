<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Harmony;
use App\Support\Http\Hosts;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Http\Response;

/**
 * /h/<sqid>: a shared harmony's page (contract/api.md, "Public pages"). No session and no cookies,
 * so it's cacheable; never indexed while unlisted. The frontend agent writes the markup
 * (resources/views/public/), this the data.
 */
final class HarmonyPageController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'public, max-age=300', 'X-Robots-Tag' => 'noindex'];

    public function __invoke(string $harmony, HarmonyFacts $facts): Response
    {
        $found = Harmony::query()->with('user', 'templateVersion.template')->where('public_id', $harmony)->first();

        if ($found === null || ! $found->isLive() || $found->media_audio_key === null) {
            $removed = $found !== null && $found->removed_at !== null;

            return response()->view('public.harmony-gone', ['reason' => $removed ? 'removed' : 'missing', 'links' => ['app' => '/']], $removed ? 410 : 404, self::HEADERS);
        }

        $id = $found->public_id;
        $ref = 'h-'.$id;
        $page = $facts->page($found);
        $title = $page['title'];
        $song = $page['song'];

        return response()->view('public.harmony', [
            'harmony' => $page,
            'meta' => [
                'title' => $title,
                'description' => $facts->description($found),
                'image' => $page['cardUrl'],
                'imageWidth' => 1200,
                'imageHeight' => 630,
                'imageAlt' => $facts->imageAlt($found),
                'url' => $page['pageUrl'],
                'audio' => $page['audioUrl'],
                // ES and other sites play it in place (contract/api.md, "/embed/h/<id>").
                'oembed' => Hosts::public('oembed?'.http_build_query(['url' => $page['pageUrl'], 'format' => 'json'])),
            ],
            'links' => [
                'learn' => $song !== null ? Hosts::app('/?'.http_build_query(['song' => $song['slug'], 'ref' => $ref])) : null,
                'app' => Hosts::app('/?'.http_build_query(['ref' => $ref])),
                // Report goes to the app's API, across origins when the hosts are apart.
                'api' => Hosts::split() ? Hosts::appUrl() : null,
            ],
            'report' => ['harmonyId' => $id],
        ], 200, self::HEADERS);
    }
}
