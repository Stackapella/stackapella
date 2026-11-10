<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Each free tool installs as its own app (docs/tools-plan.md, "Offline and installable"): its
 * manifest names it, starts on its page and keeps to its scope, with the site's icons. The tools'
 * own service worker (/tools-sw.js, scope /tools) keeps it working offline. No session; the same
 * for everyone, so it's cached like the pages.
 */
final class ToolManifestController extends Controller
{
    public function __invoke(string $tool): JsonResponse
    {
        if (! in_array($tool, (array) config('tools.slugs'), true)) {
            abort(404);
        }

        $page = '/tools/'.$tool;

        return response()->json([
            'name' => __('tools.manifest_name', ['tool' => __('tools.names.'.$tool)]),
            'short_name' => __('tools.names.'.$tool),
            'description' => __('tools.manifest_description'),
            'id' => $page,
            'start_url' => $page,
            'scope' => $page,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#000000',
            'theme_color' => '#000000',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icons/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
        ], 200, [...SongPagesController::headers(), 'Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
