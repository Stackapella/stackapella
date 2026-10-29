<?php

declare(strict_types=1);

// Cross-origin requests: only the public site's pages, to report a harmony (and read the Turnstile
// key first), without credentials (docs/app-subdomain-plan.md). Nothing else answers another
// origin's script; oEmbed is read by servers, not browsers.
return [
    'paths' => ['api/reports', 'api/auth/config'],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_origins' => array_values(array_filter([env('PUBLIC_URL')])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
