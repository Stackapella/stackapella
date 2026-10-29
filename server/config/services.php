<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Google sign-in (Socialite). M1 uses it for the admin; everyone signs in with it from M3.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
        // The native apps' OAuth client ids (one per platform), the audiences their ID tokens may name.
        'app_client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_APP_CLIENT_IDS', ''))))),
    ],

    // Cloudflare Email Service (Email Sending), for our transactional mail (MAIL_MAILER=cloudflare).
    // Stripe Connect's webhook (coaches' Express accounts): its own endpoint and signing secret.
    'stripe_connect' => [
        'webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
    ],

    // Event Schedule (M9, contract/event-schedule.md): Stackapella's OAuth client there. Without a
    // client nobody can connect by OAuth; `fake` uses App\Services\EventSchedule\FakeEventSchedule
    // instead (locally, never in production).
    // Invoice Ninja (M10): each owner's own token is kept with their connection; `fake` uses
    // App\Services\InvoiceNinja\FakeInvoiceNinja instead (locally, never in production).
    'invoice_ninja' => [
        'fake' => (bool) env('INVOICE_NINJA_FAKE', false),
    ],

    'event_schedule' => [
        'url' => env('EVENT_SCHEDULE_URL', 'https://eventschedule.com'),
        'client_id' => env('EVENT_SCHEDULE_CLIENT_ID'),
        'client_secret' => env('EVENT_SCHEDULE_CLIENT_SECRET'),
        'fake' => (bool) env('EVENT_SCHEDULE_FAKE', false),
    ],

    'cloudflare_email' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'token' => env('CLOUDFLARE_EMAIL_TOKEN'),
        // Shared with our Worker that forwards bounces and complaints to /webhooks/mail.
        'webhook_secret' => env('MAIL_WEBHOOK_SECRET'),
    ],

    // DigitalOcean's API, to purge the media CDN when a file must go at once (a guide removed).
    'digitalocean' => [
        'token' => env('DIGITALOCEAN_TOKEN'),
        'cdn_endpoint_id' => env('DIGITALOCEAN_CDN_ENDPOINT_ID'),
    ],

    // Cloudflare Turnstile, shown only while shield mode is on.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET'),
    ],

    // Sign in with Apple (socialiteproviders/apple). The client secret is made from the key.
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'redirect' => '/auth/apple/callback',
        // The native apps' bundle id: their identity tokens' audience, and the client for their codes.
        'app_bundle_id' => env('APPLE_APP_BUNDLE_ID'),
    ],

    // Plus bought in the apps (docs/app-purchases-plan.md). The App Store: the App Store Connect
    // API key for the Server API (issuer, key id, the .p8 on one line with \n), the products, and
    // Apple's root certificate the signed transactions chain to (pinned, in the repo). Sandbox
    // purchases are accepted too: App Review buys with sandbox accounts against production.
    'app_store' => [
        'issuer_id' => env('APP_STORE_ISSUER_ID'),
        'key_id' => env('APP_STORE_KEY_ID'),
        'private_key' => env('APP_STORE_PRIVATE_KEY'),
        'root_certificate' => resource_path('certs/AppleRootCA-G3.pem'),
        'products' => [
            'month' => env('APP_STORE_PRODUCT_MONTH', 'com.stackapella.plus.month'),
            'year' => env('APP_STORE_PRODUCT_YEAR', 'com.stackapella.plus.year'),
        ],
    ],

    // Google Play: the package, the service account (its JSON key, for the Play Developer API), the
    // subscription and its base plans, and who Pub/Sub's push for the real-time notifications
    // comes from (its token's audience and service account).
    // Push to the native apps (contract/api.md, "account.devices.push.update"): off until
    // APP_PUSH_ENABLED, and each service only once its keys are set. Devices may register before.
    'app_push' => [
        'enabled' => (bool) env('APP_PUSH_ENABLED', false),
    ],

    // Apple Push Notification service: a token-based (.p8) key from Apple's developer account.
    'apns' => [
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID', env('APPLE_TEAM_ID')),
        'private_key' => env('APNS_PRIVATE_KEY'),
        'topic' => env('APNS_TOPIC', env('APPLE_APP_BUNDLE_ID')),
    ],

    // Firebase Cloud Messaging (HTTP v1): the Firebase project and a service account allowed to send.
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account' => env('FCM_SERVICE_ACCOUNT'),
    ],

    'google_play' => [
        'package' => env('GOOGLE_PLAY_PACKAGE'),
        'service_account' => env('GOOGLE_PLAY_SERVICE_ACCOUNT'),
        'subscription' => env('GOOGLE_PLAY_SUBSCRIPTION', 'plus'),
        'base_plans' => ['month' => 'month', 'year' => 'year'],
        'push_audience' => env('GOOGLE_PLAY_PUSH_AUDIENCE'),
        'push_email' => env('GOOGLE_PLAY_PUSH_EMAIL'),
    ],

    // Practice reminders by web push (docs/operations-plan.md): the VAPID keys, made once with
    // `php artisan app:reminders:keys`, and the contact the push services may write to.
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT') ?: 'mailto:contact@stackapella.com',
        // The browsers' push services: a device's endpoint must be on one (never anywhere else
        // the server would then post to). A leading dot matches any subdomain.
        'push_hosts' => ['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', '.push.apple.com', '.notify.windows.com'],
        // Devices kept per person (the oldest goes for a new one past it).
        'devices_per_person' => 10,
    ],

    // Our own posts (docs/backend-plan.md D7, after iaw's app/Services/Social): one account per
    // network, so no per-site infix in the names. X is OAuth 1.0a and billed per read, so its
    // readiness check is on demand or daily, never every minute.
    'x' => [
        'api_key' => env('X_API_KEY'),
        'api_secret' => env('X_API_SECRET'),
        'access_token' => env('X_ACCESS_TOKEN'),
        'access_secret' => env('X_ACCESS_SECRET'),
    ],

    'bluesky' => [
        'handle' => env('BLUESKY_HANDLE'),
        'app_password' => env('BLUESKY_APP_PASSWORD'),
        'service' => env('BLUESKY_SERVICE', 'https://bsky.social'),
    ],

    // Our own posts (M10, docs/backend-plan.md D7) go out only in production, after Hillel approves
    // each one: anywhere else an approved post is marked as not sent.
    'social' => [
        'live' => env('APP_ENV') === 'production',
        // A featured harmony's own share video posted in place of its card: on once tried with
        // our real X and Bluesky accounts (their video uploads can't be checked without them).
        'videos' => (bool) env('SOCIAL_VIDEOS', false),
    ],

];
