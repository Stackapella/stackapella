<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The client's real IP (docs/trust-and-safety-plan.md, "The limits")
|--------------------------------------------------------------------------
|
| Requests reach the app through Cloudflare, then App Platform's load balancer, which sets
| do-connecting-ip to the address that connected to it. App\Support\Trust\ClientIp reads:
| - CF-Connecting-IP, when do-connecting-ip is one of Cloudflare's ranges below;
| - otherwise do-connecting-ip itself (a request that skipped Cloudflare);
| - and only behind App Platform (`behind_app_platform`); locally, the socket's address.
|
| A forged X-Forwarded-For or CF-Connecting-IP from anyone else is never believed. The ranges
| are Cloudflare's published list (https://www.cloudflare.com/ips/), checked weekly by
| app:check-cloudflare-ranges.
|
*/

return [

    'behind_app_platform' => (bool) (env('BEHIND_APP_PLATFORM') ?? false),

    'cloudflare' => [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],

];
