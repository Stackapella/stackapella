<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Admin access
|--------------------------------------------------------------------------
|
| Someone is an admin (or an editor) only when three independent checks agree
| (App\Support\Admin\AdminGate, after iaw's):
| 1. users.is_admin (or is_editor) is true in the database;
| 2. their google_id is listed below;
| 3. at a fresh Google sign-in in this session, Google confirmed their email, and it is listed below.
|
| A database write alone can never make an admin, and a remember-me restore never grants admin.
| Editors work the queues; money, bans, deletes, settings and switches stay with admins
| (docs/operations-plan.md, "A moderator role").
|
*/

$list = static fn (mixed $value): array => array_values(array_filter(array_map(
    static fn (string $item): string => strtolower(trim($item)),
    explode(',', is_string($value) ? $value : ''),
)));

return [

    'admin_google_ids' => $list(env('ADMIN_GOOGLE_IDS')),
    'admin_emails' => $list(env('ADMIN_EMAILS')),
    'editor_google_ids' => $list(env('EDITOR_GOOGLE_IDS')),
    'editor_emails' => $list(env('EDITOR_EMAILS')),

    /*
     * The second factor can only be turned off in the local environment; everywhere else it is
     * always required (SecondFactor::required()).
     */
    'second_factor_required' => (bool) (env('ADMIN_2FA_REQUIRED') ?? true),

    /*
     * Admin sessions: idle and absolute limits, in minutes. SESSION_LIFETIME must be at least the
     * idle limit.
     */
    'idle_timeout' => (int) (env('ADMIN_IDLE_TIMEOUT') ?: 240),
    'max_lifetime' => (int) (env('ADMIN_MAX_LIFETIME') ?: 720),

    /*
     * Sudo: sensitive actions (switches, limits, the blocklist, money, deletes) need a second-factor
     * check within this many minutes.
     */
    'sudo_window' => (int) (env('ADMIN_SUDO_WINDOW') ?: 30),

    /*
     * Wrong second-factor codes before a lockout (which doubles each time, up to an hour), and the
     * point at which every admin is emailed.
     */
    'max_second_factor_attempts' => 5,

];
