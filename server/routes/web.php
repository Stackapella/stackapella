<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\SecondFactorController;
use App\Http\Controllers\Admin\SignInController;
use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\Billing\AppStoreWebhookController;
use App\Http\Controllers\Billing\CheckoutReturnController;
use App\Http\Controllers\Billing\GooglePlayWebhookController;
use App\Http\Controllers\Billing\StripeConnectWebhookController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Books\InvoiceNinjaWebhookController;
use App\Http\Controllers\Dev\DevLoginController;
use App\Http\Controllers\EventSchedule\WebhookController as EventScheduleWebhookController;
use App\Http\Controllers\Ops\ErrorTunnelController;
use App\Http\Controllers\Ops\MailWebhookController;
use App\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| The server's own pages
|--------------------------------------------------------------------------
|
| Everything else that isn't /api, /up or a webhook is the PWA (WebAppController, the fallback in
| bootstrap/app.php). In production nginx serves the PWA's files before Laravel sees them.
|
*/

// The admin's way in (Google, then the second factor, then the admin in the app at /admin), and
// everyone's sign-in with Google or Apple.
Route::middleware('no-store')->group(function (): void {
    Route::get('/admin/sign-in', [SignInController::class, 'show'])->name('admin.sign-in');

    // Google and Apple, for everyone (contract/api.md, "Sign-in navigations").
    // Back from Stripe Checkout: a page that works signed out (contract/api.md, "Plus and billing").
    Route::get('/plus/return', CheckoutReturnController::class)->name('plus.return');
    Route::post('/auth/{provider}', [ProviderController::class, 'redirect'])->whereIn('provider', ['google', 'apple', 'event-schedule'])->middleware('throttle:provider-sign-in')->name('auth.provider');
    Route::get('/auth/google/callback', [ProviderController::class, 'googleCallback'])->middleware('throttle:provider-sign-in')->name('auth.google.callback');
    Route::get('/auth/event-schedule/callback', [ProviderController::class, 'eventScheduleCallback'])->middleware('throttle:provider-sign-in')->name('auth.event-schedule.callback');
    // Apple's cross-site POST carries no session cookie: starting one here would replace the
    // person's own, so this step runs without a session (the state cookie proves the browser).
    Route::post('/auth/apple/callback', [ProviderController::class, 'appleCallback'])->middleware('throttle:provider-sign-in')->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->name('auth.apple.callback');
    Route::get('/auth/apple/complete', [ProviderController::class, 'appleComplete'])->middleware('throttle:provider-sign-in')->name('auth.apple.complete');
});

// Anyone signed in can sign out, even when they aren't (or are no longer) an admin.
Route::post('/admin/sign-out', [SignInController::class, 'destroy'])->middleware(['auth', 'no-store'])->name('admin.sign-out');

Route::middleware(['auth', 'editor', 'no-store'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::prefix('second-factor')->name('second-factor.')->group(function (): void {
        Route::get('/setup', [SecondFactorController::class, 'setup'])->name('setup');
        Route::get('/passkey/options', [SecondFactorController::class, 'registrationOptions'])->name('passkey.options');
        Route::post('/passkey', [SecondFactorController::class, 'storePasskey'])->middleware('throttle:second-factor')->name('passkey.store');
        Route::post('/totp', [SecondFactorController::class, 'confirmTotp'])->middleware('throttle:second-factor')->name('totp');
        Route::post('/finish', [SecondFactorController::class, 'finishSetup'])->name('finish');
        Route::get('/', [SecondFactorController::class, 'challenge'])->name('challenge');
        Route::get('/challenge/options', [SecondFactorController::class, 'verificationOptions'])->name('challenge.options');
        Route::post('/challenge/passkey', [SecondFactorController::class, 'verifyPasskey'])->middleware('throttle:second-factor')->name('challenge.passkey');
        Route::post('/challenge/code', [SecondFactorController::class, 'verifyCode'])->middleware('throttle:second-factor')->name('challenge.code');
    });
});

// Working on a laptop: pick a user to sign in as (routes/uploads.php has the upload stand-in).
if (app()->environment('local')) {
    Route::get('/dev/login', [DevLoginController::class, 'index'])->name('dev.login');
    Route::post('/dev/login', [DevLoginController::class, 'store'])->name('dev.login.store');
}

// Stripe's webhook for Plus (its signature is checked; CSRF doesn't apply to webhooks/*).
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->name('webhooks.stripe');
// Stripe Connect's (coaches' Express accounts), with its own secret.
Route::post('/webhooks/stripe-connect', StripeConnectWebhookController::class)->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->name('webhooks.stripe-connect');
// The stores' notifications for Plus bought in the apps (each checked by its signature or token).
Route::post('/webhooks/app-store', AppStoreWebhookController::class)->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:webhooks')->name('webhooks.app-store');
Route::post('/webhooks/google-play', GooglePlayWebhookController::class)->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:webhooks')->name('webhooks.google-play');
// Event Schedule's appointment webhooks, one per connected schedule (each with its own secret).
// Bounces and complaints from Cloudflare Email Service, by our Worker (signed).
Route::post('/webhooks/mail', MailWebhookController::class)->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:webhooks')->name('webhooks.mail');

// The browser's errors on their way to Sentry (only for our own DSN; no session).
Route::post('/log/errors', ErrorTunnelController::class)->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:error-reports')->name('log.errors');

// Invoice Ninja's payment webhook for Stackapella's own books (Plus paid by invoice).
Route::post('/webhooks/invoice-ninja/{connection}', InvoiceNinjaWebhookController::class)->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:webhooks')->name('webhooks.invoice-ninja');
Route::post('/webhooks/event-schedule/{connection}', EventScheduleWebhookController::class)->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:webhooks')->name('webhooks.event-schedule');
