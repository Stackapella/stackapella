<?php

declare(strict_types=1);

// Running Stackapella (docs/trust-and-safety-plan.md, "Alerts"): the thresholds that email the
// admins at once (checked hourly; each alert at most every 6 hours), and when the digest goes.
return [

    'alerts' => [
        'sign_ups_an_hour' => 50,
        'reports_an_hour' => 10,
        'videos_waiting' => 20,
        'failed_payments_an_hour' => 10,
        'storage_gb_a_day' => 10,
        'limited_an_hour' => 1000,
        'suppressed_a_day' => 20,
    ],

    'alert_every_hours' => 6,

    // Slow queries are logged (docs/performance.md): one query at or over `query_ms`, and a
    // request's or a job's queries adding up to `total_ms`. Only the SQL is logged, never its values.
    'slow' => [
        'query_ms' => (int) env('SLOW_QUERY_MS', 300),
        'total_ms' => (int) env('SLOW_QUERIES_TOTAL_MS', 2000),
    ],

    // Whether search engines may index the public pages: off until launch (the beta is staging),
    // when SITE_INDEXING=true lets robots.txt and the songs, guides and legal pages be indexed.
    'indexing' => (bool) env('SITE_INDEXING', false),

    // Who security reports go to (security.txt).
    'security_contact' => env('SECURITY_CONTACT', 'contact@stackapella.com'),

    // Hosts that redirect to the public site, wholesale (the launch domain's other names: www and the
    // like). Never the beta host: after launch it's an old public host (hosts.old_public_hosts),
    // which keeps its API, the moving page and the webhooks.
    'redirect_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_REDIRECT_HOSTS', ''))))),
    // Before launch the launch domain only points at the beta's public site for a while: a
    // temporary redirect (302/307, never cached), so browsers don't keep it after launch.
    'redirect_temporary' => (bool) env('APP_REDIRECT_TEMPORARY', false),

];
