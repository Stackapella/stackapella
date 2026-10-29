<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME') ?: 'Stackapella',

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stackapella
    |--------------------------------------------------------------------------
    */

    // The deployed commit (App Platform sets APP_BUILD), shown by /up.
    'build' => env('APP_BUILD') ?: null,

    // When the image was built (.do/Dockerfile writes it), for the admin's version badge.
    'built_at' => is_file(base_path('BUILT_AT')) ? (trim((string) file_get_contents(base_path('BUILT_AT'))) ?: null) : null,

    // The PWA's files (/web). nginx serves them directly in production; Laravel answers the app's
    // routes with its index.html, and serves the files itself only where nginx doesn't
    // (php artisan serve). docs/architecture.md, "The system".
    'web_path' => env('WEB_PATH') ?: realpath(base_path('../web')) ?: base_path('../web'),

    // The beta domain before launch: the app shows "coming soon" to anyone who isn't a beta singer
    // or an admin (docs/beta-singers-plan.md, "Hosting"). Invite links and /admin always work.
    'coming_soon' => (bool) (env('COMING_SOON') ?? false),

    // The oldest app version the API still serves (X-App-Version). Older apps get a 426
    // with the code app_outdated. Empty means every version is served.
    'min_app_version' => env('MIN_APP_VERSION') ?: null,

    // Sqids for every server ID a person can see (docs/architecture.md, "Identity and IDs"). The
    // alphabet is a shuffle that is fixed for good once IDs are public: changing it breaks links.
    'sqids_alphabet' => env('SQIDS_ALPHABET') ?: 'kRwQ3nJ8ZqLzVt2bHcGmX4yPfs7DvN9TgBhW6jMxKa5CdFeYrE',

    // Whether it's set and isn't the default above, which is public with the source (the doctor
    // fails it): set to the default, it's as public as unset.
    'sqids_alphabet_set' => (string) env('SQIDS_ALPHABET') !== '' && (string) env('SQIDS_ALPHABET') !== 'kRwQ3nJ8ZqLzVt2bHcGmX4yPfs7DvN9TgBhW6jMxKa5CdFeYrE',

    // Whether this install is stackapella.com itself, the one that serves our marketing site (the
    // landing, features, pricing, about, press...; docs/branding-matrix.md). Anyone else running
    // the app under the AAL gets the app without them. IS_NEXUS says so; unset, it's worked out
    // from the addresses (ours, or local and the tests), so a deploy before the setting is safe.
    'is_nexus' => (static function (): bool {
        $set = env('IS_NEXUS');

        if ($set !== null && $set !== '') {
            return filter_var($set, FILTER_VALIDATE_BOOL);
        }

        $ours = static fn (string $url): bool => (bool) preg_match('#^https?://([a-z0-9-]+\.)*stackapella\.com(:\d+)?(/|$)#i', $url);

        return in_array(env('APP_ENV'), ['local', 'testing'], true) || $ours((string) env('APP_URL')) || $ours((string) env('PUBLIC_URL'));
    })(),

    'sqids_min_length' => 6,

];
