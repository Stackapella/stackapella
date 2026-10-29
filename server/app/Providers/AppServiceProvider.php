<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminSecondFactor;
use App\Models\AuditLog;
use App\Models\BetaAssignment;
use App\Models\BetaFeedback;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Block;
use App\Models\ChoirVoice;
use App\Models\CoachProfile;
use App\Models\Comment;
use App\Models\DataExport;
use App\Models\EarlyAccessLink;
use App\Models\EmailSuppression;
use App\Models\EmailToken;
use App\Models\Engagement;
use App\Models\Flag;
use App\Models\FlagUser;
use App\Models\HandleHold;
use App\Models\Harmony;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\MudeoBatch;
use App\Models\Notice;
use App\Models\PartScore;
use App\Models\Report;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\ShareVideo;
use App\Models\SongRequest;
use App\Models\SupportMessage;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserDevice;
use App\Observers\EngagementLedger;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\StripeGateway;
use App\Services\Coaching\CoachingGateway;
use App\Services\Coaching\StripeCoachingGateway;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\FakeEventSchedule;
use App\Services\EventSchedule\HttpEventSchedule;
use App\Services\InvoiceNinja\FakeInvoiceNinja;
use App\Services\InvoiceNinja\HttpInvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Services\Mail\CloudflareTransport;
use App\Support\Admin\AdminGate;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Ops\TempFiles;
use App\Support\Trust\Limits;
use App\Support\Usage\Plans;
use App\Support\Web\AppShell;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Passkeys\Passkeys;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

final class AppServiceProvider extends ServiceProvider
{
    /** The job a worker is running, for the slow-query log. */
    private static ?string $job = null;

    public function register(): void
    {
        // Our webhook route (/webhooks/stripe, App\Http\Controllers\Billing\StripeWebhookController),
        // not Cashier's; Plus is kept while Stripe retries a payment; Stripe Tax works out the tax.
        Cashier::ignoreRoutes();

        // One per request: it works out which projects are read-only once.
        $this->app->scoped(Plans::class);
        $this->app->scoped(Gallery::class);

        // Stripe, only through the gateway (the tests bind a fake).
        $this->app->bind(BillingGateway::class, StripeGateway::class);
        $this->app->bind(CoachingGateway::class, StripeCoachingGateway::class);
        // The fake stands in until Event Schedule's side is live, but never in production.
        $this->app->bind(EventSchedule::class, fn (): EventSchedule => config('services.event_schedule.fake') === true && ! $this->app->isProduction() ? new FakeEventSchedule : new HttpEventSchedule);
        $this->app->bind(InvoiceNinja::class, fn (): InvoiceNinja => config('services.invoice_ninja.fake') === true && ! $this->app->isProduction() ? new FakeInvoiceNinja : new HttpInvoiceNinja);
        Cashier::keepPastDueSubscriptionsActive();
        Cashier::calculateTaxes();

        // Passkeys are only the admin's second factor, with routes inside the admin area
        // (routes/web.php). The package's own sign-in and management routes would be a second way
        // in, so they're never registered: this must run before the package boots.
        Passkeys::ignoreRoutes();

        $this->app->singleton(AdminGate::class);
        $this->app->singleton(AppShell::class);
        $this->app->scoped(Flags::class);
    }

    public function boot(): void
    {
        // A queue worker sweeps its own /tmp of files a killed job left (at most once an hour).
        Queue::looping(static function (): void {
            TempFiles::sweepHourly();
        });

        // Strict models outside production: lazy loading, silently discarded attributes and missing
        // attributes all throw, which catches N+1 queries and typos while developing.
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->logSlowQueries();

        // Morph aliases are stored in the database (audit_logs.subject_type), so they never change.
        // Coaching's money into the ledger as it moves (the books and our commission read it).
        Engagement::observe(EngagementLedger::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'beta_singer' => BetaSinger::class,
            'beta_assignment' => BetaAssignment::class,
            'beta_submission' => BetaSubmission::class,
            'beta_feedback' => BetaFeedback::class,
            'template_guide' => TemplateGuide::class,
            'admin_second_factor' => AdminSecondFactor::class,
            'audit_log' => AuditLog::class,
            'block' => Block::class,
            'email_suppression' => EmailSuppression::class,
            'email_token' => EmailToken::class,
            'handle_hold' => HandleHold::class,
            'user_device' => UserDevice::class,
            'data_export' => DataExport::class,
            'flag' => Flag::class,
            'flag_user' => FlagUser::class,
            'security_event' => SecurityEvent::class,
            'setting' => Setting::class,
            'template' => Template::class,
            'template_version' => TemplateVersion::class,
            'harmony' => Harmony::class,
            'share_video' => ShareVideo::class,
            'report' => Report::class,
            'coach_profile' => CoachProfile::class,
            'notice' => Notice::class,
            'support_message' => SupportMessage::class,
            'early_access_link' => EarlyAccessLink::class,
            'mudeo_batch' => MudeoBatch::class,
            'comment' => Comment::class,
            'idea' => Idea::class,
            'idea_voice' => IdeaVoice::class,
            'choir_voice' => ChoirVoice::class,
            'song_request' => SongRequest::class,
            'part_score' => PartScore::class,
        ]);

        // The contract's envelope is { "data": ... }; lists add their own `meta`.
        JsonResource::wrap('data');

        $this->configureRateLimiting();

        // Sign in with Apple (socialiteproviders/apple).
        Event::listen(static fn (SocialiteWasCalled $event) => $event->extendSocialite('apple', AppleProvider::class));

        Mail::extend('cloudflare', static fn (): CloudflareTransport => new CloudflareTransport(
            (string) config('services.cloudflare_email.account_id'),
            (string) config('services.cloudflare_email.token'),
        ));
    }

    /**
     * Every named limiter in config/limits.php, with Hillel's live overrides (App\Support\Trust\Limits).
     */
    private function configureRateLimiting(): void
    {
        foreach (array_keys((array) config('limits')) as $name) {
            RateLimiter::for((string) $name, fn (Request $request): array => $this->app->make(Limits::class)->for((string) $name, $request));
        }
    }

    /**
     * Slow queries in the log (config/ops.php's `slow`): the SQL with its placeholders (never the
     * values, which can be personal), its time and where it ran.
     */
    private function logSlowQueries(): void
    {
        $total = (int) config('ops.slow.total_ms');

        DB::listen(static function (QueryExecuted $query): void {
            $each = (int) config('ops.slow.query_ms');

            if ($each > 0 && $query->time >= $each) {
                Log::warning('Slow query', ['ms' => round($query->time), 'sql' => mb_substr($query->sql, 0, 2000), 'where' => self::where()]);
            }
        });

        if ($total > 0) {
            DB::whenQueryingForLongerThan($total, static function (Connection $connection) use ($total): void {
                Log::warning('Slow queries in all', ['over_ms' => $total, 'ms' => round($connection->totalQueryDuration()), 'where' => self::where()]);
            });
        }

        // A worker runs many jobs: each one's queries are added up on their own, and named by it.
        Queue::before(static function (JobProcessing $event) use ($total): void {
            self::$job = $event->job->resolveName();

            if ($total > 0) {
                DB::connection()->resetTotalQueryDuration();
                DB::connection()->allowQueryDurationHandlersToRunAgain();
            }
        });
        Queue::after(static function (): void {
            self::$job = null;
        });
        Queue::exceptionOccurred(static function (): void {
            self::$job = null;
        });
    }

    /**
     * The route or job a query ran in, for the slow-query log.
     */
    private static function where(): string
    {
        $request = app()->runningInConsole() ? null : app('request');
        $route = $request instanceof Request ? $request->route()?->getName() : null;

        return match (true) {
            $route !== null => 'route '.$route,
            self::$job !== null => 'job '.self::$job,
            app()->runningInConsole() => 'console '.implode(' ', array_slice((array) ($_SERVER['argv'] ?? []), 1, 2)),
            default => 'request',
        };
    }
}
