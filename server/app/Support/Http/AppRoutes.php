<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * The app's own addresses (web/js/router.js's routes, by their first part), so the public site can
 * tell an old link to an app page from an address that was never anything. On the public site's host
 * an app page gets "Stackapella has moved" (it brings what the device kept there to the app's host);
 * any other address that isn't a page is a real 404, never the app's shell with a 200 (which search
 * engines read as a page). A test checks every route in router.js is here.
 */
final class AppRoutes
{
    public const PATHS = [
        'p/*', 'admin', 'admin/*', 'styleguide', 'styleguide/*', 'settings', 'settings/*', 'storage', 'voice', 'workout',
        'plus', 'plus/*', 'class', 'class/*', 'learn', 'learn/*', 'beta', 'beta/*', 'create', 'create/*', 't/*',
        'join/*', 'early/*', 'invite/*', 'groups', 'groups/*', 'choir/*', 'challenge/*', 'moved', 'contact', 'cooldown',
        'warmup', 'start-fresh', 'ideas', 'ideas/*', 'requests', 'voices',
        // Signing in and the emails' links (the router's auth pages and HostRouting's redirected ones).
        'signin', 'signin/*', 'verify', 'reset', 'email/*', 'notice', 'auth/*', 'preview/*', 'teacher/*', 'connect/*',
    ];

    public static function is(Request $request): bool
    {
        // The site's root with ?app is an old install opening the app (its start_url).
        return ($request->path() === '/' && $request->query->has('app')) || $request->is(...self::PATHS);
    }
}
