<?php

declare(strict_types=1);

/*
 * Outreach (docs/outreach-plan.md, docs/outreach-setup.md): one-to-one emails Hillel sends to
 * organizations, from their own subdomain and Cloudflare sending stream so a spam mark can never
 * touch sign-in emails. Off (no send, 409 outreach_off) until the sender and its token are set.
 */
return [
    // hillel@hello.stackapella.com: the subdomain with its own SPF, DKIM and DMARC.
    'from' => env('MAIL_OUTREACH_FROM'),
    'from_name' => env('MAIL_OUTREACH_FROM_NAME', 'Hillel Coren'),
    // Where replies go: the from address (its Email Routing Worker logs each reply and forwards it
    // to Hillel), or Hillel's own inbox (then replies are logged only if he forwards them there).
    'reply_to' => env('MAIL_OUTREACH_REPLY_TO'),
    // Cloudflare Email Service for the subdomain: its own API token (and account, if not the app's).
    'account_id' => env('MAIL_OUTREACH_CLOUDFLARE_ACCOUNT_ID', env('CLOUDFLARE_ACCOUNT_ID')),
    'token' => env('MAIL_OUTREACH_CLOUDFLARE_TOKEN'),
    // Shared with the Worker that posts deliveries, bounces and replies to /webhooks/outreach.
    'webhook_secret' => env('MAIL_OUTREACH_WEBHOOK_SECRET'),

    // A reply from one of these is never matched to an organization by its domain alone (everyone
    // has an address there), so a stranger's "stop" can't speak for one.
    'free_mail_domains' => ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com'],

    // Hillel: "5 a day"; "One, after 10 days".
    'daily_cap' => (int) env('OUTREACH_DAILY_CAP', 5),
    'follow_up_days' => 10,

    // The page each angle's link opens (with ?ref=<the organization's tag>).
    'angles' => [
        'school' => '/for/music-teachers',
        'choir_groups' => '/for/choir-directors',
        'course' => '/courses',
        'practice_tool' => '/',
    ],
];
