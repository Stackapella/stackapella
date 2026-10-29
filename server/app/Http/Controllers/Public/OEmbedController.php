<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /oembed?url=<a /h/<id> link>&format=json: how Event Schedule (and any oEmbed consumer) shows
 * a shared harmony as a player, `/embed/h/<id>` in an iframe (contract/api.md, "/embed/h/<id>").
 * Only our own links, only live harmonies.
 */
final class OEmbedController extends Controller
{
    private const WIDTH = 480;

    private const HEIGHT = 160;

    public function __invoke(Request $request, HarmonyFacts $facts): JsonResponse
    {
        $format = $request->query('format', 'json');

        if ($format !== 'json') {
            return response()->json(['error' => 'Only json.'], 501);
        }

        $url = $request->query('url');
        $ours = Hosts::publicHost();
        $parts = is_string($url) ? parse_url($url) : false;
        $id = is_array($parts) && ($parts['host'] ?? null) === $ours && preg_match('#^/h/([A-Za-z0-9]{6,24})/?$#', (string) ($parts['path'] ?? ''), $m) === 1 ? $m[1] : null;
        $harmony = $id !== null ? HarmonyFacts::live($id) : null;

        if ($harmony === null) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $width = min(self::WIDTH, max(200, (int) $request->query('maxwidth', (string) self::WIDTH)));
        $height = min(self::HEIGHT, max(100, (int) $request->query('maxheight', (string) self::HEIGHT)));
        $page = $facts->page($harmony);
        $src = Hosts::public('embed/h/'.$harmony->public_id);

        return response()->json([
            'type' => 'rich',
            'version' => '1.0',
            'title' => $page['title'],
            'author_name' => $facts->names($harmony),
            'provider_name' => 'Stackapella',
            'provider_url' => Hosts::publicUrl(),
            'thumbnail_url' => $page['cardUrl'],
            'thumbnail_width' => 1200,
            'thumbnail_height' => 630,
            'html' => '<iframe src="'.e($src).'" width="'.$width.'" height="'.$height.'" style="border:0" loading="lazy" allow="autoplay" title="'.e($page['title']).'"></iframe>',
            'width' => $width,
            'height' => $height,
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }
}
