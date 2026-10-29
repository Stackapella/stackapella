<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Beta\AcceptInvite;
use App\Actions\Counts\Funnel;
use App\Actions\Users\RegisterWithPassword;
use App\Actions\Users\ResendVerification;
use App\Actions\Users\ResetPassword;
use App\Actions\Users\SignInWithPassword;
use App\Actions\Users\StartPasswordReset;
use App\Actions\Users\VerifyEmail;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SignInRequest;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\AccountResource;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\Cloudflare\Turnstile;
use App\Services\EventSchedule\EventSchedule;
use App\Support\Accounts\Age;
use App\Support\Accounts\BetaPass;
use App\Support\Accounts\Sessions;
use App\Support\Accounts\SignInSession;
use App\Support\Flags;
use App\Support\Media\Instruments;
use App\Support\Trust\Blocklist;
use App\Support\Trust\Country;
use App\Support\Trust\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * auth.*: email accounts, signing in and out (contract/api.md, "Accounts and sign-in"). Sign-up,
 * resend and reset answer the same whether or not an address has an account.
 */
final class AuthController extends Controller
{
    public function __construct(private readonly SignInSession $signInSession) {}

    public function config(Request $request, Flags $flags, Turnstile $turnstile, EventSchedule $eventSchedule): JsonResponse
    {
        $paused = $flags->value('pause_signups');

        // The app asks this on every start: once a day, the signed-in account's country.
        if (($user = $request->user()) instanceof User) {
            Country::daily($user, $request);
        }

        return response()->json(['data' => [
            // Event Schedule only once its OAuth client is set up (or locally, its fake).
            'methods' => [...['apple', 'google'], ...($eventSchedule->configured() ? ['event_schedule'] : []), 'password'],
            'signups' => match ($paused) {
                'password' => 'password_paused',
                'all' => 'paused',
                default => 'open',
            },
            'turnstileSiteKey' => $turnstile->siteKey(),
            'passwordMinLength' => StrongPassword::MIN,
            // Before launch, for someone not let in yet (no beta pass, not signed in): the app shows
            // only signing in and the invite pages.
            'comingSoon' => config('app.coming_soon') === true && ! BetaPass::valid($request) && $request->user() === null,
            // Before launch, for everyone (comingSoon is false for anyone let in): the app shows
            // the draft songs until then.
            'prelaunch' => config('app.coming_soon') === true,
            // Where the apps find the instrument samples' index; null where instruments are off or none is uploaded yet.
            'instrumentsIndexUrl' => config('templates.instruments') === true ? Instruments::listedIndexUrl() : null,
            // A self-hosted install (config/selfhost.php): every account has Plus, so no Plus screens.
            'selfHosted' => config('selfhost.enabled') === true,
            // Whether the app sends its anonymous usage counts (counts.create).
            'usageCounts' => config('counts.enabled') === true,
            // The funnel's running A/B experiment (App\Actions\Counts\Funnel): a new device draws an arm
            // at its first open while `enrol` is true. Null when none runs or counts are off.
            'experiment' => app(Funnel::class)->current(),
            // Whether coaches can apply (the coach_signup switch), for the app's and the site's Apply.
            'coachSignup' => $flags->on('coach_signup'),
            // The browser error reporter's DSN (public by design: its envelopes come to /log/errors).
            'sentryDsn' => is_string($dsn = config('sentry.browser_dsn')) && $dsn !== '' ? $dsn : null,
        ]]);
    }

    public function register(RegisterRequest $request, RegisterWithPassword $register, Blocklist $blocklist, SecurityLog $securityLog): JsonResponse
    {
        if (($block = $blocklist->find(email: (string) $request->validated('email'), ip: $request->ip())) !== null) {
            $securityLog->record(SecurityEventType::Blocked, null, $request->ip(), ['where' => 'sign_up', 'block' => $block->id]);

            throw new Refusal(403, 'blocked');
        }

        // Under 13: refused, and nothing kept (not even their answer).
        $birth = ['month' => (int) $request->validated('birthMonth'), 'year' => (int) $request->validated('birthYear')];

        Age::ensureOldEnough($birth['month'], $birth['year']);

        // A bot: the honeypot filled, or the form sent in under 2 seconds (timed on the device, so
        // no clock skew). Same answer, nothing done.
        $elapsedMs = $request->validated('elapsedMs');
        $tooQuick = is_int($elapsedMs) && $elapsedMs < 2000;

        if ($request->validated('nickname') === null && ! $tooQuick) {
            $invite = $request->validated('invite');
            $early = $request->validated('early');
            $register->handle((string) $request->validated('name'), (string) $request->validated('email'), (string) $request->validated('password'), $request->validated('ref'), app()->getLocale(), $request->ip(), $birth, is_string($invite) && $invite !== '' ? $invite : null, is_string($early) && $early !== '' ? $early : null, Country::of($request));
        }

        return $this->checkEmail();
    }

    public function verify(TokenRequest $request, VerifyEmail $verify, AcceptInvite $acceptInvite): AccountResource
    {
        $user = $verify->handle((string) $request->validated('token'));
        $this->signInSession->start($request, $user, 'verify');
        // The beta invite the sign-up carried, now that the address is proven.
        $acceptInvite->pending($user);

        return new AccountResource($user);
    }

    public function resend(EmailRequest $request, ResendVerification $resend): JsonResponse
    {
        $resend->handle((string) $request->validated('email'), $request->ip());

        return $this->checkEmail();
    }

    public function signIn(SignInRequest $request, SignInWithPassword $signIn, Blocklist $blocklist, SecurityLog $securityLog): AccountResource
    {
        if (($block = $blocklist->find(email: (string) $request->validated('email'), ip: $request->ip())) !== null) {
            $securityLog->record(SecurityEventType::Blocked, null, $request->ip(), ['where' => 'sign_in', 'block' => $block->id]);

            throw new Refusal(403, 'blocked');
        }

        $user = $signIn->handle((string) $request->validated('email'), (string) $request->validated('password'), $request->ip());
        $this->signInSession->start($request, $user, 'password');

        return new AccountResource($user);
    }

    public function signOut(Request $request): Response
    {
        // This device only: the others stay remembered (signing out everywhere rotates the token).
        Auth::logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function signOutEverywhere(Request $request, Sessions $sessions): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $sessions->endAll($user);
        }

        return $this->signOut($request);
    }

    public function forgot(EmailRequest $request, StartPasswordReset $start, Blocklist $blocklist): JsonResponse
    {
        if ($blocklist->find(email: (string) $request->validated('email'), ip: $request->ip()) === null) {
            $start->handle((string) $request->validated('email'));
        }

        return $this->checkEmail();
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $reset, AcceptInvite $acceptInvite): AccountResource
    {
        $user = $reset->handle((string) $request->validated('email'), (string) $request->validated('token'), (string) $request->validated('password'), $request->ip());
        $this->signInSession->start($request, $user, 'reset');
        // Signing up again over an unverified account ends here (the set-password link).
        $acceptInvite->pending($user);

        return new AccountResource($user);
    }

    private function checkEmail(): JsonResponse
    {
        return response()->json(['data' => ['status' => 'check_email']], 202);
    }
}
