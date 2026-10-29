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

Schedule::command('queue:work --queue=default --stop-when-empty --max-time=55 --tries=3')
    ->name('queue:default')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// First every minute, so a slow task before it never makes the scheduler look stopped.
Schedule::command('app:heartbeat')
    ->name('heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('app:prune-security-events')
    ->name('prune-security-events')
    ->dailyAt('03:10')
    ->withoutOverlapping(30)
    ->runInBackground();

// Only where requests come through Cloudflare (config/selfhost.php): a self-hosted install makes
// no request of its own.
Schedule::command('app:check-cloudflare-ranges')
    ->name('check-cloudflare-ranges')
    ->when(static fn (): bool => config('selfhost.behind_cloudflare') === true)
    ->weeklyOn(1, '04:20')
    ->withoutOverlapping(10);

Schedule::command('app:beta:prune-masters')
    ->name('beta-prune-masters')
    ->hourlyAt(25)
    ->withoutOverlapping(30);

Schedule::command('app:accounts:prune')
    ->name('accounts-prune')
    ->dailyAt('03:30')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:leagues:weekly')
    ->name('leagues-weekly')
    ->weeklyOn(1, '00:10')
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('app:choirs:mix')
    ->name('choirs-mix')
    ->hourlyAt(45)
    ->withoutOverlapping(30);

Schedule::command('app:coaches:refresh')
    ->name('coaches-refresh')
    ->hourlyAt(35)
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:ideas:notify')
    ->name('ideas-notify')
    ->hourlyAt(25)
    ->withoutOverlapping(30);

Schedule::command('app:mudeo:prune')
    ->name('mudeo-prune')
    ->dailyAt('03:55')
    ->withoutOverlapping(30);

Schedule::command('app:cache:prune')
    ->name('cache-prune')
    ->dailyAt('03:50')
    ->withoutOverlapping(30)
    ->runInBackground();

// Laravel's own: reset links past their 60 minutes.
Schedule::command('auth:clear-resets')
    ->name('clear-password-resets')
    ->dailyAt('03:40')
    ->withoutOverlapping(10);

// Laravel's own: failed jobs (their payloads name people and things) after 30 days, as the privacy
// policy says (docs/privacy-audit.md).
Schedule::command('queue:prune-failed --hours=720')
    ->name('prune-failed-jobs')
    ->dailyAt('03:45')
    ->withoutOverlapping(10);

Schedule::command('app:bin:purge')
    ->name('bin-purge')
    ->hourlyAt(35)
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('app:reminders:send')
    ->name('practice-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping(14);

Schedule::command('app:sharing:prune')
    ->name('sharing-prune')
    ->hourlyAt(45)
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('app:coaching:deadlines')
    ->name('coaching-deadlines')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('app:event-schedule:reconcile')
    ->name('event-schedule-reconcile')
    ->hourlyAt(20)
    ->withoutOverlapping(50)
    ->runInBackground();

Schedule::command('app:books:commission')
    ->name('books-commission')
    ->monthlyOn(1, '06:10')
    ->withoutOverlapping(60)
    ->runInBackground();

Schedule::command('app:store:tokens')
    ->name('store-tokens')
    ->everyMinute()
    ->withoutOverlapping(5);

// The bell's pushes to the native apps (nothing while APP_PUSH_ENABLED is off).
Schedule::command('app:push:send')
    ->name('app-push-send')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('app:store:acknowledge')
    ->name('store-acknowledge')
    ->hourlyAt(15)
    ->withoutOverlapping(30);

Schedule::command('app:social:month')
    ->name('social-month')
    ->dailyAt('08:30')
    ->withoutOverlapping(30);

Schedule::command('app:social:send')
    ->name('social-send')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('app:books:sweep')
    ->name('books-sweep')
    ->hourlyAt(35)
    ->withoutOverlapping(50)
    ->runInBackground();

Schedule::command('app:readiness --alert')
    ->name('readiness')
    ->everyTenMinutes()
    ->withoutOverlapping(9);

Schedule::command('app:alerts')
    ->name('alerts')
    ->hourlyAt(5)
    ->withoutOverlapping(30);

Schedule::command('app:digest')
    ->name('digest')
    ->dailyAt('07:00')
    ->withoutOverlapping(30);

Schedule::command('app:backup:run')
    ->name('backup-database')
    ->dailyAt('03:10')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('app:backup:run --files')
    ->name('backup-files')
    ->weeklyOn(0, '04:10')
    ->withoutOverlapping(600)
    ->runInBackground();

Schedule::command('app:backup:drill')
    ->name('backup-drill')
    ->monthlyOn(2, '05:10')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('app:stats:nightly')
    ->name('stats-nightly')
    ->dailyAt('03:40')
    ->withoutOverlapping(60)
    ->runInBackground();

// Someone whose birth month and year now make them 18 becomes an adult on their own.
Schedule::command('app:accounts:ages')
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
