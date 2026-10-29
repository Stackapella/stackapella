<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        // Cloudflare Email Service (App\Services\Mail\CloudflareTransport), registered in AppServiceProvider.
        'cloudflare' => [
            'transport' => 'cloudflare',
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        // A second provider is a config change, not a rewrite (docs/backend-plan.md, D9): set
        // MAIL_MAILER=failover, with the backup's SMTP settings, if Cloudflare's beta lets us down.
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'cloudflare',
                'smtp',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    // The one email to mudeo's users (docs/mudeo-transition-plan.md) goes from stackapella.com
    // itself (Hillel, 2026-10-29: "we don't have mudeo domain any more"), through Cloudflare Email
    // Sending, whose SPF and DKIM already cover it. Sending refuses until the postal address (the
    // law's footer) is set; MAIL_MUDEO_READY=false can still hold it back.
    'mudeo' => [
        'address' => env('MAIL_MUDEO_FROM') ?: 'hillel@stackapella.com',
        'reply_to' => env('MAIL_MUDEO_REPLY_TO') ?: 'contact@stackapella.com',
        // Unset or empty: ready (stackapella.com is onboarded); false holds it back.
        'ready' => in_array(env('MAIL_MUDEO_READY'), [null, ''], true) || (bool) env('MAIL_MUDEO_READY'),
        'postal_address' => env('MAIL_POSTAL_ADDRESS'),
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'contact@stackapella.com'),
        'name' => env('MAIL_FROM_NAME') ?: (env('APP_NAME') ?: 'Stackapella'),
    ],

];
