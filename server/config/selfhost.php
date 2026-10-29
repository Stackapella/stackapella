<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Running Stackapella yourself
|--------------------------------------------------------------------------
| docs/open-source-plan.md, 5.5, and the README's "Running it yourself". Everything here is off on
| stackapella.com: nothing changes for the hosted service unless SELF_HOSTED is set.
*/

$selfHosted = filter_var(env('SELF_HOSTED', false), FILTER_VALIDATE_BOOL);
$cloudflare = env('BEHIND_CLOUDFLARE');

return [

    // A self-hosted install: every account has Plus (App\Support\Usage\Plans), and the legal pages
    // are the operator's own (App\Support\Legal\LegalText), never Stackapella's.
    'enabled' => $selfHosted,

    // Whether requests reach the server through Cloudflare. Only then does the weekly check of its
    // published IP ranges run (app:check-cloudflare-ranges): on for stackapella.com, off by default
    // on a self-hosted install, so a fresh install calls out to no one on its own.
    'behind_cloudflare' => $cloudflare === null || $cloudflare === '' ? ! $selfHosted : filter_var($cloudflare, FILTER_VALIDATE_BOOL),

    // The operator's legal texts, laid out as resources/legal is (en/terms.md, en/privacy.md...,
    // each with its front matter). Until one is written, its page says so.
    'legal_path' => env('SELF_HOSTED_LEGAL_PATH') ?: resource_path('legal/local'),

];
