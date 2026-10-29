<?php

declare(strict_types=1);

use App\Support\Errors\ScrubEvent;

// Sentry (docs/backend-plan.md A3): off without a DSN. Nothing personal and no audio is ever sent:
// ScrubEvent strips requests, people and addresses before an event leaves.
return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('APP_BUILD'),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    'sample_rate' => 1.0,

    // No performance tracing at launch (it would send every request's path and timing).
    'traces_sample_rate' => null,

    'send_default_pii' => false,

    'before_send' => [ScrubEvent::class, 'event'],

    'before_breadcrumb' => [ScrubEvent::class, 'breadcrumb'],

    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'sql_transaction' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => false,
        'notifications' => false,
    ],

    'tracing' => [
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'views' => false,
        'http_client_requests' => false,
        'redis_commands' => false,
        'default_integrations' => false,
    ],

    // The browser's errors come through our own tunnel (/log/errors), for this DSN only.
    'browser_dsn' => env('SENTRY_BROWSER_DSN'),

    // The scheduled jobs with a Sentry cron monitor, by name (routes/console.php): the plan has
    // one, so only the nightly backup by default. The rest are covered by the heartbeat and the
    // doctor (docs/runbook.md, "Scheduled jobs").
    'cron_monitors' => array_values(array_filter(array_map('trim', explode(',', (string) env('SENTRY_CRON_MONITORS', 'backup-database'))))),

];
