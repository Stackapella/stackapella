<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The two hosts (docs/app-subdomain-plan.md)
|--------------------------------------------------------------------------
|
| The app (APP_URL: app.stackapella.com) and the public site (PUBLIC_URL: beta.stackapella.com
| until launch, then stackapella.com), served by one Laravel app that routes by host. Unset, or
| the same as APP_URL, everything is on one host (local development and the tests).
|
*/

return [
    'public_url' => env('PUBLIC_URL'),

    // The beta pass is read on both hosts before launch (".stackapella.com"); unset, the host's own.
    'pass_domain' => env('BETA_PASS_DOMAIN'),

    // The public site's old hosts (beta.stackapella.com once PUBLIC_URL is stackapella.com): never
    // a blanket redirect, because they still serve what can't move by redirect. Old installs keep
    // their songs in that origin's storage, so its API and the moving page stay; the webhooks
    // pointed at it keep answering (Stripe doesn't follow redirects); and its public pages, the
    // shared /h/ links among them, go to the public site with their path (docs/launch-day.md).
    'old_public_hosts' => array_values(array_filter(array_map(static fn (string $h): string => strtolower(trim($h)), explode(',', (string) env('OLD_PUBLIC_HOSTS', ''))))),
];
