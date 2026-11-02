<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| The schedule (each named, with a bounded withoutOverlapping)
|--------------------------------------------------------------------------
|
| The scheduler worker (.do/app.yaml) runs schedule:work. The jobs SENTRY_CRON_MONITORS names (the
| nightly backup by default) report to Sentry's cron monitors (at the end). The `default` queue (mail, light jobs)
| is drained here every minute, as iaw does; ffmpeg work has its own resident worker on `long`
| (docs/conventions.md, "Two queues"). Slow tasks run in the background, so the minute's quick ones
| (the heartbeat, the queue, coaching deadlines, posts) never wait behind them.
|
*/

// Every task runs on one server only (its per-minute lock is in the shared cache), so a deploy
// that briefly runs the old and the new scheduler together never does anything twice.
Schedule::command('queue:work --queue=default --stop-when-empty --max-time=55 --tries=3')
    ->onOneServer()
    ->name('queue:default')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// First every minute, so a slow task before it never makes the scheduler look stopped.
Schedule::command('app:heartbeat')
    ->onOneServer()
    ->name('heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('app:prune-security-events')
    ->onOneServer()
    ->name('prune-security-events')
    ->dailyAt('03:10')
    ->withoutOverlapping(30)
    ->runInBackground();

// Only where requests come through Cloudflare (config/selfhost.php): a self-hosted install makes
// no request of its own.
Schedule::command('app:check-cloudflare-ranges')
    ->onOneServer()
    ->name('check-cloudflare-ranges')
    ->when(static fn (): bool => config('selfhost.behind_cloudflare') === true)
    ->weeklyOn(1, '04:20')
    ->withoutOverlapping(10);

Schedule::command('app:beta:prune-masters')
    ->onOneServer()
    ->name('beta-prune-masters')
    ->hourlyAt(25)
    ->withoutOverlapping(30);

Schedule::command('app:accounts:prune')
    ->onOneServer()
    ->name('accounts-prune')
    ->dailyAt('03:30')
    ->withoutOverlapping(30)
    ->runInBackground();

// The experiments' one early stop: a guardrail worse at 99% closes a test.
Schedule::command('app:code-watch:search')
    ->onOneServer()
    ->name('code-watch')
    ->weeklyOn(1, '06:10')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:search-console:sync')
    ->onOneServer()
    ->name('search-console')
    ->dailyAt('05:40')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:counts:active-singers')
    ->onOneServer()
    ->name('active-singers')
    ->hourlyAt(35)
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:experiments:guard')
    ->onOneServer()
    ->name('experiments-guard')
    ->dailyAt('04:20')
    ->withoutOverlapping(30)
    ->runInBackground();

// Lend your voice: claims that ran out with nothing sent go back on the Wanted list.
Schedule::command('app:voices:release')
    ->onOneServer()
    ->name('voices-release')
    ->dailyAt('04:40')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:boards:prune-daily')
    ->onOneServer()
    ->name('boards-prune-daily')
    ->dailyAt('03:40')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:leagues:weekly')
    ->onOneServer()
    ->name('leagues-weekly')
    // Daily, though a week's leagues form once: a missed Monday is caught the next day.
    ->dailyAt('00:10')
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('app:choirs:mix')
    ->onOneServer()
    ->name('choirs-mix')
    ->hourlyAt(45)
    ->withoutOverlapping(30);

Schedule::command('app:coaches:refresh')
    ->onOneServer()
    ->name('coaches-refresh')
    ->hourlyAt(35)
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:ideas:notify')
    ->onOneServer()
    ->name('ideas-notify')
    ->hourlyAt(25)
    ->withoutOverlapping(30);

Schedule::command('app:mudeo:prune')
    ->onOneServer()
    ->name('mudeo-prune')
    ->dailyAt('03:55')
    ->withoutOverlapping(30);

Schedule::command('app:cache:prune')
    ->onOneServer()
    ->name('cache-prune')
    ->dailyAt('03:50')
    ->withoutOverlapping(30)
    ->runInBackground();

// Laravel's own: reset links past their 60 minutes.
Schedule::command('auth:clear-resets')
    ->onOneServer()
    ->name('clear-password-resets')
    ->dailyAt('03:40')
    ->withoutOverlapping(10);

// Laravel's own: failed jobs (their payloads name people and things) after 30 days, as the privacy
// policy says (docs/privacy-audit.md).
Schedule::command('queue:prune-failed --hours=720')
    ->onOneServer()
    ->name('prune-failed-jobs')
    ->dailyAt('03:45')
    ->withoutOverlapping(10);

Schedule::command('app:bin:purge')
    ->onOneServer()
    ->name('bin-purge')
    ->hourlyAt(35)
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('app:reminders:send')
    ->onOneServer()
    ->name('practice-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping(14);

Schedule::command('app:sharing:prune')
    ->onOneServer()
    ->name('sharing-prune')
    ->hourlyAt(45)
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:coaching:deadlines')
    ->onOneServer()
    ->name('coaching-deadlines')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('app:event-schedule:reconcile')
    ->onOneServer()
    ->name('event-schedule-reconcile')
    ->hourlyAt(20)
    ->withoutOverlapping(50)
    ->runInBackground();

Schedule::command('app:books:commission')
    ->onOneServer()
    ->name('books-commission')
    ->monthlyOn(1, '06:10')
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('app:store:tokens')
    ->onOneServer()
    ->name('store-tokens')
    ->everyMinute()
    ->withoutOverlapping(5);

// The bell's pushes to the native apps (nothing while APP_PUSH_ENABLED is off).
Schedule::command('app:push:send')
    ->onOneServer()
    ->name('app-push-send')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('app:store:acknowledge')
    ->onOneServer()
    ->name('store-acknowledge')
    ->hourlyAt(15)
    ->withoutOverlapping(30);

Schedule::command('app:social:month')
    ->onOneServer()
    ->name('social-month')
    ->dailyAt('08:30')
    ->withoutOverlapping(30);

Schedule::command('app:social:send')
    ->onOneServer()
    ->name('social-send')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('app:books:sweep')
    ->onOneServer()
    ->name('books-sweep')
    ->hourlyAt(35)
    ->withoutOverlapping(50)
    ->runInBackground();

Schedule::command('app:readiness --alert')
    ->onOneServer()
    ->name('readiness')
    ->everyTenMinutes()
    ->withoutOverlapping(9);

Schedule::command('app:alerts')
    ->onOneServer()
    ->name('alerts')
    ->hourlyAt(5)
    ->withoutOverlapping(30);

Schedule::command('app:digest')
    ->onOneServer()
    ->name('digest')
    // Hourly, though it goes once a day, from 07:00 report time: a missed run is caught.
    ->hourlyAt(2)
    ->withoutOverlapping(30);

Schedule::command('app:backup:run')
    ->onOneServer()
    ->name('backup-database')
    ->dailyAt('03:10')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('app:backup:run --files')
    ->onOneServer()
    ->name('backup-files')
    ->weeklyOn(0, '04:10')
    ->withoutOverlapping(600)
    ->runInBackground();

Schedule::command('app:backup:drill')
    ->onOneServer()
    ->name('backup-drill')
    ->monthlyOn(2, '05:10')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('app:stats:nightly')
    ->onOneServer()
    ->name('stats-nightly')
    ->dailyAt('03:40')
    ->withoutOverlapping(60)
    ->runInBackground();

// Someone whose birth month and year now make them 18 becomes an adult on their own.
Schedule::command('app:accounts:ages')
    ->onOneServer()
    ->name('accounts-ages')
    ->dailyAt('00:20')
    ->withoutOverlapping(30);

// Sentry's cron monitors, only for the jobs config('sentry.cron_monitors') names (the plan allows
// few): each reports a missed or failed run, a no-op without a DSN.
foreach (Schedule::events() as $event) {
    if (in_array($event->description, (array) config('sentry.cron_monitors'), true)) {
        $event->sentryMonitor();
    }
}
