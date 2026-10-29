<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Passkeys (laravel/passkeys)
|--------------------------------------------------------------------------
|
| Passkeys are only the admin's second factor (App\Support\Admin\SecondFactor), with routes inside
| the admin area. The package's own sign-in and management routes are never registered
| (Passkeys::ignoreRoutes() in AppServiceProvider), so the route settings it reads are left out.
|
*/

// The app's own host, and its registrable domain (the last two labels: stackapella.com from
// app.stackapella.com; a bare host such as localhost stays as it is).
$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$appHost = (string) parse_url($appUrl, PHP_URL_HOST);
$scheme = (string) (parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');
$labels = explode('.', $appHost);
$registrable = count($labels) >= 2 && ! filter_var($appHost, FILTER_VALIDATE_IP) ? implode('.', array_slice($labels, -2)) : $appHost;
$rpId = (string) (env('PASSKEYS_RP_ID') ?: $registrable);
$publicUrl = rtrim((string) env('PUBLIC_URL', ''), '/');

return [

    // The parent domain, not the app's host (docs/decisions.md, "passkeys on the parent
    // domain"): a passkey registered on stackapella.com works on app., beta. and any later host,
    // so moving the app never locks an admin out again.
    'relying_party_id' => $rpId,

    // Every origin a ceremony may come from: the app, the public site and the bare domain.
    'allowed_origins' => array_values(array_unique(array_filter([
        $appUrl,
        $publicUrl,
        $scheme.'://'.$rpId,
    ]))),

    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET') ?: config('app.key'),

    'timeout' => 60000,

    'guard' => 'web',

    'middleware' => ['web'],

    'management_middleware' => [],

    'throttle' => null,

    'redirect' => '/admin',

];
