<?php

declare(strict_types=1);

namespace App\Support\Ops;

use App\Actions\Books\Books;
use App\Console\Commands\BackupDrill;
use App\Models\EsConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\Template;
use App\Services\Audio\Ffmpeg;
use App\Services\EventSchedule\EventSchedule;
use App\Services\Social\SocialPoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Everything Stackapella needs to run, checked (docs/backend-plan.md A3; after iaw's readiness):
 * the configuration, the database, the queue and the scheduler, storage, mail, payments, the
 * integrations, backups and error reporting. Each is pass, warn or fail with its fix. Run by
 * `app:readiness` (every 10 minutes with --alert, emailing changes) and `app:doctor`.
 *
 * On a self-hosted install (config/selfhost.php) it checks only what that install needs: any mailer
 * will do, and Stackapella's own services (payments, its books, its X and Bluesky, the Cloudflare
 * bounce Worker) are left out, as are fixes that point at our own docs.
 */
final class Readiness
{
    /** Settings that keep when something last happened (the heartbeat, a backup, the drill). */
    public const HEARTBEAT = 'ops.heartbeat_at';

    public const BACKUP = 'ops.backup';

    public const DRILL = 'ops.restore_drill';

    public function __construct(private readonly Ffmpeg $ffmpeg, private readonly EventSchedule $eventSchedule, private readonly SocialPoster $poster) {}

    /**
     * @return list<Check>
     */
    public function run(): array
    {
        $production = app()->isProduction();
        $selfHosted = config('selfhost.enabled') === true;
        $checks = [];

        // The configuration.
        $checks[] = Check::when(! (bool) config('app.debug') || ! $production, 'config', 'debug', 'Debug mode', 'Off.', 'APP_DEBUG is on in production: errors show their details to anyone.', 'Set APP_DEBUG=false.');
        $checks[] = Check::when((string) config('app.key') !== '', 'config', 'key', 'App key', 'Set.', 'APP_KEY is missing: sessions and encrypted tokens can\'t work.', 'php artisan key:generate'.($selfHosted ? ', and keep it safe.' : ', then keep it in App Platform.'));
        $checks[] = Check::when(str_starts_with((string) config('app.url'), 'https://') || ! $production, 'config', 'url', 'App address', (string) config('app.url'), 'APP_URL isn\'t https.', 'Set APP_URL to the https address.');
        $checks[] = Check::when((bool) config('session.secure') || ! $production, 'config', 'secure-cookies', 'Secure cookies', 'Sent over https only.', 'SESSION_SECURE_COOKIE is off: the session cookie could travel over plain http.', 'Set SESSION_SECURE_COOKIE=true.');
        $checks[] = Check::when((bool) config('app.sqids_alphabet_set') || ! $production, 'config', 'sqids', 'Link alphabet', 'Set.', 'SQIDS_ALPHABET isn\'t set, or is the default: ids and links use the alphabet in the public source.', 'Set SQIDS_ALPHABET once, before launch, and never change it'.($selfHosted ? '.' : ' (docs/runbook.md, "The secrets").'));
        $checks[] = Check::when((string) config('app.timezone') === 'UTC', 'config', 'timezone', 'Time zone', 'UTC.', 'The app isn\'t on UTC: stored times would shift.', 'Set APP_TIMEZONE=UTC.');

        // The database and the queue.
        $checks[] = $this->safely('database', 'connection', 'Database', function (): Check {
            DB::select('select 1');
            $pending = $this->pendingMigrations();

            return Check::when($pending === 0, 'database', 'connection', 'Database', 'Connected; migrations up to date.', "{$pending} migrations haven't run.", 'php artisan migrate --force');
        });
        $checks[] = $this->safely('queue', 'backlog', 'Queue', function (): Check {
            $oldest = DB::table('jobs')->where('available_at', '<=', now()->getTimestamp())->min('available_at');
            $waiting = is_numeric($oldest) ? (int) round((now()->getTimestamp() - (int) $oldest) / 60) : 0;
            $failed = DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())->count();

            return match (true) {
                $waiting > 15 => new Check('queue', 'backlog', 'Queue', Check::FAIL, "The oldest job has waited {$waiting} minutes.", 'Check the queue worker is running (and its logs).'),
                $failed > 10 => new Check('queue', 'backlog', 'Queue', Check::WARN, "{$failed} jobs failed in the last day.", 'Look at failed_jobs; php artisan queue:retry once fixed.'),
                default => Check::pass('queue', 'backlog', 'Queue', 'Moving; '.$failed.' failed in the last day.'),
            };
        });
        $heartbeat = $this->time(self::HEARTBEAT);
        $checks[] = Check::when($heartbeat !== null && $heartbeat->gt(now()->subMinutes(3)), 'queue', 'scheduler', 'Scheduler', 'Last beat '.$heartbeat?->diffForHumans().'.', $heartbeat === null ? 'The scheduler has never run here.' : 'The scheduler last ran '.$heartbeat->diffForHumans().'.', 'Check the scheduler'.($selfHosted ? ' (php artisan schedule:work).' : ' (php artisan schedule:work, or its job in App Platform).'));

        // Storage, audio and mail.
        foreach (['media', 'private', 'backup'] as $disk) {
            $checks[] = $this->safely('storage', $disk, 'Storage: '.$disk, function () use ($disk): Check {
                $probe = 'tmp/readiness-'.Str::random(8).'.txt';
                Storage::disk($disk)->put($probe, 'ok');
                $read = Storage::disk($disk)->get($probe) === 'ok';
                Storage::disk($disk)->delete($probe);

                return Check::when($read, 'storage', $disk, 'Storage: '.$disk, 'Writable.', 'A file written couldn\'t be read back.', 'Check the bucket and its keys.');
            });
        }
        $checks[] = $this->safely('audio', 'ffmpeg', 'ffmpeg', function (): Check {
            $missing = $this->ffmpeg->missingEncoders();

            return Check::when($missing === [], 'audio', 'ffmpeg', 'ffmpeg', 'With libx264 and aac.', 'ffmpeg lacks '.implode(', ', $missing).': share videos and AAC fail.', 'Install ffmpeg with those encoders (the Aptfile).');
        });
        $mailer = (string) config('mail.default');
        $checks[] = $selfHosted
            ? Check::when(! $production || ! in_array($mailer, ['log', 'array'], true), 'mail', 'transport', 'Email', 'Sending through '.$mailer.'.', 'MAIL_MAILER is '.$mailer.': no email leaves the server (sign-in links, verification).', 'Set MAIL_MAILER and its settings: any Laravel mailer (SMTP, SES, Postmark, Resend).')
            : Check::when(! $production || ($mailer === 'cloudflare' && (string) config('services.cloudflare_email.token') !== ''), 'mail', 'transport', 'Email', 'Sending through '.$mailer.'.', 'Production isn\'t sending through Cloudflare Email Service with a token.', 'Set MAIL_MAILER=cloudflare, CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_EMAIL_TOKEN.');

        // The songs, from their own repo: none, and the library and the courses are empty.
        $checks[] = $this->safely('content', 'songs', 'Songs', function (): Check {
            $songs = Template::query()->official()->count();

            return Check::when($songs > 0, 'content', 'songs', 'Songs', "{$songs} in the library.", 'No songs in the library.', 'sh server/tool/content.sh fetch (from the repo root), then php artisan app:templates:sync.');
        });

        $checks[] = Check::when(! $production || ((string) config('services.turnstile.site_key') !== '' && (string) config('services.turnstile.secret') !== ''), 'config', 'turnstile', 'Turnstile', 'Keys set.', 'No Turnstile keys: shield mode can\'t ask for a challenge when sign-ups or reports surge.', 'Set TURNSTILE_SITE_KEY and TURNSTILE_SECRET.', Check::WARN);

        if (! $selfHosted) {
            $this->ours($checks, $production);
        } elseif ($this->eventSchedule->configured()) {
            // Optional on an install of your own: checked once it's set up.
            $reconnect = EsConnection::query()->where('status', EsConnection::NEEDS_RECONNECT)->count();
            $checks[] = Check::when($reconnect === 0, 'integrations', 'event_schedule', 'Event Schedule', 'Set up; every connection working.', "{$reconnect} connections need reconnecting.", 'Those coaches see "Reconnect Event Schedule".', Check::WARN);
        }

        $checks[] = Check::when((string) config('services.vapid.public_key') !== '' && (string) config('services.vapid.private_key') !== '', 'integrations', 'push', 'Practice reminders', 'VAPID keys set.', 'No VAPID keys: practice reminders can\'t be sent.', 'php artisan app:reminders:keys, then set VAPID_*.', Check::WARN);

        // Backups and errors.
        $backup = $this->time(self::BACKUP);
        $checks[] = Check::when($backup !== null && $backup->gt(now()->subHours(26)), 'backups', 'database', 'Database backup', 'Last one '.$backup?->diffForHumans().'.', $backup === null ? 'No backup has been made here.' : 'The last backup is '.$backup->diffForHumans().'.', 'php artisan app:backup:run, and check its schedule.', $production ? Check::FAIL : Check::WARN);
        $drill = $this->time(self::DRILL);
        $checks[] = Check::when($drill !== null && $drill->gt(now()->subDays(35)), 'backups', 'drill', 'Restore drill', 'Last one passed '.$drill?->diffForHumans().'.', $drill === null ? 'No restore drill has passed.' : 'The last drill that passed was '.$drill->diffForHumans().'.', 'php artisan app:backup:drill (monthly).', Check::WARN);
        $drillDatabase = (string) config('database.restore_drill_database');

        if ($drillDatabase !== '') {
            $checks[] = $this->safely('backups', 'drill_database', 'Drill database', function () use ($drillDatabase): Check {
                $unsafe = BackupDrill::unsafe($drillDatabase, (string) DB::connection()->getDatabaseName());

                if ($unsafe !== null) {
                    return new Check('backups', 'drill_database', 'Drill database', Check::FAIL, $unsafe, 'Point RESTORE_DRILL_DATABASE at a scratch database.');
                }

                // Proven, not assumed: the app's user makes and drops a table there.
                $table = 'readiness_'.Str::lower(Str::random(8));
                DB::statement("create table `{$drillDatabase}`.`{$table}` (id int primary key)");
                DB::statement("drop table `{$drillDatabase}`.`{$table}`");

                return Check::pass('backups', 'drill_database', 'Drill database', "The app can fill and empty {$drillDatabase}.");
            });
        }

        if (! $selfHosted) {
            $checks[] = Check::when((string) config('services.cloudflare_email.webhook_secret') !== '', 'mail', 'bounces', 'Bounce webhook', 'Set up.', 'Not set up: bounces are recorded only when a send reports them.', 'Deploy the bounce Worker and set MAIL_WEBHOOK_SECRET (docs/runbook.md).', Check::WARN);
        }
        $checks[] = Check::when((string) config('sentry.dsn') !== '', 'backups', 'sentry', 'Error reporting', 'Sentry is on.', 'Not set up: errors are only in the logs.', 'Set SENTRY_LARAVEL_DSN (and SENTRY_BROWSER_DSN).', Check::WARN);

        return $checks;
    }

    /**
     * Stackapella's own services: payments, Event Schedule, its books and its X and Bluesky. Not
     * checked on a self-hosted install.
     *
     * @param  list<Check>  $checks
     */
    private function ours(array &$checks, bool $production): void
    {
        // Payments.
        $stripe = (string) config('cashier.secret') !== '';
        $checks[] = Check::when($stripe, 'payments', 'stripe', 'Stripe', 'Keys set.', 'No Stripe keys: Plus and coaching can\'t be bought.', 'Set STRIPE_KEY and STRIPE_SECRET (Needs Hillel item 22).', $production ? Check::FAIL : Check::WARN);
        $checks[] = Check::when(! $stripe || (string) config('cashier.webhook.secret') !== '', 'payments', 'stripe_webhook', 'Stripe webhook', 'Its secret is set.', 'Stripe has keys but no webhook secret: production refuses every event.', 'Set STRIPE_WEBHOOK_SECRET.');
        $checks[] = Check::when(! $stripe || (string) config('services.stripe_connect.webhook_secret') !== '', 'payments', 'connect_webhook', 'Stripe Connect webhook', 'Its secret is set.', 'No Connect webhook secret: coaches can\'t finish onboarding.', 'Set STRIPE_CONNECT_WEBHOOK_SECRET (item 30).', Check::WARN);

        // Integrations.
        $reconnect = EsConnection::query()->where('status', EsConnection::NEEDS_RECONNECT)->count();
        $checks[] = Check::when($this->eventSchedule->configured() && $reconnect === 0, 'integrations', 'event_schedule', 'Event Schedule', 'Set up; every connection working.', ! $this->eventSchedule->configured() ? 'No OAuth client: nobody can connect Event Schedule.' : "{$reconnect} connections need reconnecting.", ! $this->eventSchedule->configured() ? 'Set EVENT_SCHEDULE_CLIENT_ID and _SECRET (item 34).' : 'Those coaches see "Reconnect Event Schedule"; see admin.eventSchedule.show.', Check::WARN);
        $platform = Books::platformBooks();
        $stuck = InvoiceNinjaRecord::query()->where('status', InvoiceNinjaRecord::FAILED)->where('updated_at', '<', now()->subDay())->count();
        $checks[] = Check::when($platform !== null && $stuck === 0, 'integrations', 'invoice_ninja', 'Invoice Ninja', 'Stackapella\'s books connected; nothing stuck.', $platform === null ? 'Stackapella\'s books aren\'t connected.' : "{$stuck} recordings have failed for over a day.", $platform === null ? 'Connect them in /admin, Books (item 37).' : 'See the books\' "not recorded" lists.', Check::WARN);
        $networks = array_keys($this->poster->networks());
        $failedPosts = SocialPost::query()->where('status', SocialPost::FAILED)->where('updated_at', '>', now()->subWeek())->count();
        $checks[] = Check::when(count($networks) === 2 && $failedPosts === 0, 'integrations', 'social', 'Our X and Bluesky', 'Both set up.', count($networks) < 2 ? ($networks === [] ? 'Neither has its keys.' : 'Only '.($networks[0] === 'x' ? 'X' : 'Bluesky').' has its keys.') : "{$failedPosts} posts failed this week.", count($networks) < 2 ? 'Set the X_* and BLUESKY_* keys (item 37).' : 'Retry them in /admin, Posts.', Check::WARN);
    }

    /**
     * @param  list<Check>  $checks
     * @return array{pass: int, warn: int, fail: int}
     */
    public static function summary(array $checks): array
    {
        $count = static fn (string $status): int => count(array_filter($checks, static fn (Check $c): bool => $c->status === $status));

        return ['pass' => $count(Check::PASS), 'warn' => $count(Check::WARN), 'fail' => $count(Check::FAIL)];
    }

    public static function stamp(string $key, mixed $value = null): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value ?? now()->utc()->toIso8601ZuluString()]);
    }

    private function time(string $key): ?Carbon
    {
        $value = Setting::query()->where('key', $key)->first()?->value;
        $at = is_array($value) ? ($value['at'] ?? null) : $value;

        return is_string($at) && $at !== '' ? Carbon::parse($at) : null;
    }

    private function pendingMigrations(): int
    {
        Artisan::call('migrate:status', ['--pending' => true]);

        return substr_count(Artisan::output(), 'Pending');
    }

    /**
     * @param  callable(): Check  $check
     */
    private function safely(string $group, string $key, string $label, callable $check): Check
    {
        try {
            return $check();
        } catch (Throwable $e) {
            return new Check($group, $key, $label, Check::FAIL, mb_strimwidth($e->getMessage(), 0, 200, '...'), 'Look at the error above.');
        }
    }
}
