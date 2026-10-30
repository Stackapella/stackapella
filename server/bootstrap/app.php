<?php

declare(strict_types=1);

use App\Exceptions\Refusal;
use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Ops\SecurityTxtController;
use App\Http\Controllers\PreviewLinkController;
use App\Http\Controllers\Public\CoachesPageController;
use App\Http\Controllers\Public\CoachPageController;
use App\Http\Controllers\Public\ComparePagesController;
use App\Http\Controllers\Public\CoursePagesController;
use App\Http\Controllers\Public\DocumentPagesController;
use App\Http\Controllers\Public\EmbedHarmonyController;
use App\Http\Controllers\Public\FeaturePagesController;
use App\Http\Controllers\Public\ForPagesController;
use App\Http\Controllers\Public\GalleryPagesController;
use App\Http\Controllers\Public\HarmonyPageController;
use App\Http\Controllers\Public\InfoPagesController;
use App\Http\Controllers\Public\OEmbedController;
use App\Http\Controllers\Public\OpenSourcePagesController;
use App\Http\Controllers\Public\SingTogetherPagesController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\SitePagesController;
use App\Http\Controllers\Public\SongPagesController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\WebAppController;
use App\Http\Middleware\AuthenticateAppDevice;
use App\Http\Middleware\CheckAppVersion;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAgeAnswered;
use App\Http\Middleware\EnsureBetaSinger;
use App\Http\Middleware\EnsureEditor;
use App\Http\Middleware\EnsureSecondFactor;
use App\Http\Middleware\EnsureSudo;
use App\Http\Middleware\GrantBetaPass;
use App\Http\Middleware\HostRouting;
use App\Http\Middleware\NexusOnly;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\PausedFeature;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\ReadOnlyMode;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\RenewRememberCookie;
use App\Http\Middleware\ResolveClientIp;
use App\Http\Middleware\Revalidate;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionlessAnonymousReads;
use App\Http\Middleware\Shield;
use App\Services\EventSchedule\EventScheduleError;
use App\Support\Accounts\BetaPass;
use App\Support\Admin\IntendedUrl;
use App\Support\Http\ApiError;
use App\Support\Pages\MudeoArrival;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as LaravelPreventRequestForgery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function (): void {
            Route::get('/up', HealthController::class)->middleware('throttle:health')->name('health');
            Route::get('/.well-known/security.txt', SecurityTxtController::class)->middleware('throttle:public-pages')->name('security.txt');

            Route::middleware('web')->group(base_path('routes/web.php'));

            Route::group([], base_path('routes/uploads.php'));

            Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));

            // The public pages: no session and no cookies, so a visit leaves nothing behind and the
            // page can be cached (contract/api.md, "Public pages").
            Route::get('/h/{harmony}', HarmonyPageController::class)->middleware('throttle:public-pages')->name('harmony.page');
            Route::get('/coaches', CoachesPageController::class)->middleware('throttle:public-pages')->name('coaches.page');
            Route::get('/coach/{handle}', CoachPageController::class)->where('handle', '[a-z][a-z0-9_]{2,19}')->middleware('throttle:public-pages')->name('coach.page');
            // Songs, guides and legal pages for search, and the sitemap (no session; cacheable).
            Route::get('/songs', [SongPagesController::class, 'index'])->middleware(['nexus', 'throttle:public-pages'])->name('songs.index');
            Route::get('/songs/{slug}', [SongPagesController::class, 'show'])->where('slug', '[a-z0-9-]{1,80}')->middleware(['nexus', 'throttle:public-pages'])->name('songs.page');
            Route::get('/guides/{slug}', [DocumentPagesController::class, 'guide'])->where('slug', '[a-z0-9-]{1,80}')->middleware(['nexus', 'throttle:public-pages'])->name('guides.page');
            Route::get('/legal/{page}', [DocumentPagesController::class, 'legal'])->where('page', '[a-z0-9-]{1,80}')->middleware('throttle:public-pages')->name('legal.page');
            // The gallery's public pages (N1, behind its flag).
            Route::get('/@{handle}', [GalleryPagesController::class, 'profile'])->where('handle', '[A-Za-z][A-Za-z0-9_]{2,19}')->middleware('throttle:public-pages')->name('profile.page');
            Route::get('/explore', [GalleryPagesController::class, 'explore'])->middleware('throttle:public-pages')->name('explore.page');
            Route::get('/ideas/{idea}', [SingTogetherPagesController::class, 'idea'])->where('idea', '[A-Za-z0-9]{12}')->middleware('throttle:public-pages')->name('idea.page');
            Route::get('/choirs/{song}', [SingTogetherPagesController::class, 'choir'])->where('song', '[a-z0-9-]{1,80}')->middleware('throttle:public-pages')->name('choir.page');
            // The landing (at / for a first-time visitor, from the app shell), pricing and help.
            // What it does and who it's for (docs/public-site-plan.md).
            Route::get('/features', [FeaturePagesController::class, 'index'])->middleware(['nexus', 'throttle:public-pages'])->name('features.index');
            Route::get('/features/{slug}', [FeaturePagesController::class, 'show'])->where('slug', '[a-z0-9-]{1,40}')->middleware(['nexus', 'throttle:public-pages'])->name('features.page');
            Route::get('/compare', [ComparePagesController::class, 'index'])->middleware(['nexus', 'throttle:public-pages'])->name('compare.index');
            Route::get('/compare/{slug}', [ComparePagesController::class, 'show'])->where('slug', '[a-z0-9-]{1,40}')->middleware(['nexus', 'throttle:public-pages'])->name('compare.page');
            Route::get('/about', [SitePagesController::class, 'about'])->middleware(['nexus', 'throttle:public-pages'])->name('about.page');
            Route::get('/how-it-works', [SitePagesController::class, 'howItWorks'])->middleware(['nexus', 'throttle:public-pages'])->name('how.page');
            Route::get('/press', [SitePagesController::class, 'press'])->middleware(['nexus', 'throttle:public-pages'])->name('press.page');
            // Open source (docs/open-source-plan.md, 5.2 and 5.3): there only with the open_source flag on.
            Route::get('/open-source', [OpenSourcePagesController::class, 'openSource'])->middleware(['nexus', 'throttle:public-pages'])->name('open-source.page');
            Route::get('/selfhost', [OpenSourcePagesController::class, 'selfHost'])->middleware(['nexus', 'throttle:public-pages'])->name('selfhost.page');
            Route::permanentRedirect('/self-host', '/selfhost')->middleware('nexus')->name('selfhost.moved');
            Route::permanentRedirect('/self-host-stackapella', '/selfhost')->middleware('nexus')->name('selfhost.moved-long');
            Route::get('/guides', [SitePagesController::class, 'guides'])->middleware(['nexus', 'throttle:public-pages'])->name('guides.index');
            Route::get('/whats-new', [SitePagesController::class, 'whatsNew'])->middleware(['nexus', 'throttle:public-pages'])->name('whats-new.page');
            Route::get('/for/{who}', [ForPagesController::class, 'show'])->where('who', '[a-z0-9-]{1,40}')->middleware(['nexus', 'throttle:public-pages'])->name('for.page');
            Route::get('/courses', [CoursePagesController::class, 'index'])->middleware(['nexus', 'throttle:public-pages'])->name('courses.index');
            Route::get('/courses/{slug}', [CoursePagesController::class, 'show'])->where('slug', '[a-z0-9-]{1,40}')->middleware(['nexus', 'throttle:public-pages'])->name('courses.page');
            Route::get('/pricing', [InfoPagesController::class, 'pricing'])->middleware(['nexus', 'throttle:public-pages'])->name('pricing.page');
            Route::get('/help', [InfoPagesController::class, 'help'])->middleware('throttle:public-pages')->name('help.index');
            Route::get('/help/search-index.json', [InfoPagesController::class, 'helpSearch'])->middleware('throttle:public-pages')->name('help.search');
            Route::get('/help/{slug}', [InfoPagesController::class, 'helpPage'])->where('slug', '[a-z0-9-]{1,40}')->middleware('throttle:public-pages')->name('help.page');
            Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->middleware(['nexus', 'throttle:public-pages'])->name('sitemap');
            Route::get('/robots.txt', [SitemapController::class, 'robots'])->middleware('throttle:public-pages')->name('robots');

            // The mudeo email's one-click unsubscribe (RFC 8058): signed, no session. GET shows the
            // button (no data-changing GET); the POST, from it or from the mail provider, does it.
            // Hillel's preview link: opens the app before launch without an account (the beta pass).
            Route::get('/preview/{code}', [PreviewLinkController::class, 'show'])->middleware('throttle:invite-links')->name('preview.link');
            Route::post('/preview/{code}', [PreviewLinkController::class, 'open'])->middleware('throttle:invite-links')->name('preview.open');

            Route::get('/unsubscribe/{contact}', [UnsubscribeController::class, 'show'])->middleware(['signed:relative', 'throttle:public-pages'])->name('mudeo.unsubscribe');
            Route::post('/unsubscribe/{contact}', [UnsubscribeController::class, 'store'])->middleware(['signed:relative', 'throttle:public-pages'])->name('mudeo.unsubscribe.store');
            // Ideas' emails to their writer (docs/your-tune-plan.md): the same one click turns them off.
            Route::get('/unsubscribe/ideas/{user}', [UnsubscribeController::class, 'showIdeas'])->middleware(['signed:relative', 'throttle:public-pages'])->name('ideas.unsubscribe');
            Route::post('/unsubscribe/ideas/{user}', [UnsubscribeController::class, 'storeIdeas'])->middleware(['signed:relative', 'throttle:public-pages'])->name('ideas.unsubscribe.store');

            // A harmony played on other sites (Event Schedule's event pages): oEmbed and its player.
            Route::get('/oembed', OEmbedController::class)->middleware('throttle:public-pages')->name('oembed');
            Route::get('/embed/h/{harmony}', EmbedHarmonyController::class)->middleware('throttle:public-pages')->name('embed.harmony');

            // Everything else is the PWA: its index.html for app routes, and locally its files. No
            // session: an anonymous visit never makes a session row or sets a cookie (the app's
            // first /api call gets the XSRF-TOKEN cookie when it needs one).
            Route::fallback(WebAppController::class);
        },
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First of all: the client's real IP, so everything after it (limits, security events,
        // the audit log) sees the person, not a proxy (App\Support\Trust\ClientIp).
        $middleware->prepend(ResolveClientIp::class);
        // The launch domain's other names go to the one in APP_URL before anything else.
        $middleware->prepend(RedirectToCanonicalHost::class);
        $middleware->append(SecurityHeaders::class);
        // The app's host and the public site's (docs/app-subdomain-plan.md).
        $middleware->append(HostRouting::class);

        // Only the protocol comes from the proxy (App Platform terminates TLS). The client IP is
        // ResolveClientIp's job, so a forged X-Forwarded-For can never dodge a limit.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);

        // Before the api group copies the web group, so the API checks CSRF with ours too.
        $middleware->web(append: [RenewRememberCookie::class], replace: [LaravelPreventRequestForgery::class => PreventRequestForgery::class]);

        // The API is the web stack (the session cookie and CSRF on one origin; contract/api.md),
        // plus the app version check, read-only mode, the backstop limiter and no-store.
        $middleware->group('api', [
            // The native apps' bearer token first, so the session and CSRF know it's an app.
            AuthenticateAppDevice::class,
            // An anonymous read starts no session (auth.config apart), so cookie-less clients
            // don't write a session row each time.
            SessionlessAnonymousReads::class,
            ...$middleware->getMiddlewareGroups()['web'],
            CheckAppVersion::class,
            ReadOnlyMode::class,
            'throttle:api',
            NoStore::class,
            GrantBetaPass::class,
        ]);

        // Laravel's order, except that a too-old app is told so first, and the limiters count
        // before sign-in is checked, so floods of signed-out requests are limited too.
        $middleware->priority([
            CheckAppVersion::class,
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ThrottleRequests::class,
            AuthenticatesRequests::class,
            AuthenticatesSessions::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'age' => EnsureAgeAnswered::class,
            'beta' => EnsureBetaSinger::class,
            'editor' => EnsureEditor::class,
            'second-factor' => EnsureSecondFactor::class,
            'sudo' => EnsureSudo::class,
            'no-store' => NoStore::class,
            'revalidate' => Revalidate::class,
            'paused' => PausedFeature::class,
            'shield' => Shield::class,
            'nexus' => NexusOnly::class,
        ]);

        // Webhooks are signed instead (Stripe, Event Schedule, from later milestones), and Apple's
        // sign-in returns by a cross-site POST, checked by its own state cookie.
        // Read by the app shell's coming-soon gate, which runs without the cookie middleware: the
        // beta pass is signed by us, and the app's own marker holds nothing.
        $middleware->encryptCookies(except: [BetaPass::COOKIE, MudeoArrival::COOKIE, MudeoArrival::USED_COOKIE, 'sa_app']);

        // Reports also come from the public site's pages, across origins and without the app's
        // XSRF cookie (docs/app-subdomain-plan.md, "Report from a shared page").
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'auth/apple/callback', 'api/reminders/devices/*', 'api/reports', 'preview/*', 'unsubscribe/*']);

        $middleware->redirectGuestsTo(fn (Request $request): string => route('admin.sign-in', ['intended' => $request->getRequestUri()]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errors to Sentry when it's set up (config/sentry.php scrubs them first).
        Integration::handles($exceptions);

        // A stale CSRF token on a sign-in form (POST /auth/google or /auth/apple) goes back to the
        // app's page with ?authError=expired, not to a 419 page outside the app.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $request->routeIs('auth.provider')) {
                return null;
            }

            $intended = $request->input('intended');

            return ProviderController::back(str_starts_with(is_string($intended) ? $intended : '', '/admin') ? IntendedUrl::admin($intended) : IntendedUrl::app($intended), 'expired');
        });

        // The API's one error shape (contract/schemas/error.json), with a stable code the app
        // words itself. Never a raw exception message.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api', 'api/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                // A response someone built on purpose (a limiter's refusal) goes out as it is.
                $e instanceof HttpResponseException => $e->getResponse(),
                $e instanceof Refusal => ApiError::response($e->getStatusCode(), $e->refusalCode, $e->extra, $e->getHeaders()),
                // Event Schedule refused or is out of reach (contract/api.md, M9).
                $e instanceof EventScheduleError => ApiError::response($e->status(), $e->reason),
                $e instanceof ValidationException => ApiError::response(422, 'validation_failed', ['errors' => $e->errors()]),
                $e instanceof AuthenticationException => ApiError::response(401, 'unauthenticated'),
                // No such route or method: nothing there. (A missing record, a refused policy and a
                // stale CSRF token reach here already turned into 404, 403 and 419 by Laravel.)
                $e instanceof MethodNotAllowedHttpException => ApiError::response(404, 'not_found'),
                $e instanceof ThrottleRequestsException => ApiError::response(429, 'rate_limited', ['retryAfter' => (int) ($e->getHeaders()['Retry-After'] ?? 60)], array_map('strval', $e->getHeaders())),
                $e instanceof HttpExceptionInterface => ApiError::response($e->getStatusCode(), ApiError::codeFor($e->getStatusCode()), [], array_map('strval', $e->getHeaders())),
                default => ApiError::response(500, 'server_error'),
            };
        });

        // Never flashed into the session on a failed validation: second-factor codes and secrets.
        $exceptions->dontFlash(['code', 'credential', 'secret', 'token', 'password', 'password_confirmation']);

        $exceptions->dontReportDuplicates();
    })->create();
