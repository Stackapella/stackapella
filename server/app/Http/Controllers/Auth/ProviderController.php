<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Beta\AcceptInvite;
use App\Actions\Users\SignInWithProvider;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StartProviderSignInRequest;
use App\Models\EsConnection;
use App\Models\User;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\EventScheduleError;
use App\Services\EventSchedule\HttpEventSchedule;
use App\Support\Accounts\SignInSession;
use App\Support\Admin\AdminGate;
use App\Support\Admin\IntendedUrl;
use App\Support\Admin\SecondFactor;
use App\Support\Trust\Blocklist;
use App\Support\Trust\Country;
use App\Support\Trust\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use SocialiteProviders\Apple\Provider as AppleProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Sign in with Google or Apple (iaw's LoginController, for everyone from M3): a form POST leaves
 * the app, the provider sends the person back, and the server returns them to the app page they
 * came from with `?signedIn=google` or `?authError=<code>` (contract/api.md, "Sign-in
 * navigations"). Google's account picker always shows, its email must be verified, and accounts
 * match only on the provider's id. From Settings (`link=1`), the provider is added to the
 * signed-in account instead.
 *
 * Apple returns by a cross-site POST, which the session cookie (SameSite=lax) doesn't travel on,
 * so its callback checks the state from a short-lived cookie of its own, then hands the result to
 * a same-site GET (`/auth/apple/complete`), where the session is.
 */
final class ProviderController extends Controller
{
    public const YOUR_GOOGLE_ID = 'admin.your_google_id';

    private const PROVIDERS = ['google', 'apple', 'event-schedule'];

    private const APPLE_STATE = 'stackapella_apple';

    public function __construct(
        private readonly SignInWithProvider $signIn,
        private readonly SignInSession $signInSession,
        private readonly Blocklist $blocklist,
        private readonly SecurityLog $securityLog,
        private readonly SecondFactor $secondFactor,
        private readonly AcceptInvite $acceptInvite,
        private readonly EventSchedule $eventSchedule,
    ) {}

    public function redirect(StartProviderSignInRequest $request, string $provider): SymfonyRedirect
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $intended = IntendedUrl::app($request->validated('intended'));
        $current = $request->user();
        $link = $request->boolean('link') && $current instanceof User;

        // Adding a way in to an account needs a recent sign-in, like changing the password.
        if ($link && ! SignInSession::isRecent($request)) {
            return self::back($intended, 'recent_sign_in_required');
        }

        // Linking is bound to the account that asked, never to whoever is signed in at the end.
        $invite = $request->validated('invite');
        $early = $request->validated('early');
        $pending = ['intended' => $intended, 'link' => $link, 'linkUser' => $link ? $current->id : null, 'ref' => $request->validated('ref'), 'invite' => is_string($invite) && $invite !== '' ? $invite : null, 'early' => is_string($early) && $early !== '' ? $early : null];

        if ($provider === 'apple') {
            $state = Str::random(40);
            // Sent to Apple and checked in its id_token on the way back, which a stateless callback
            // needs (the state cookie can't cross Apple's form post in every browser).
            $nonce = Str::random(40);
            Cache::put('auth:apple:'.$state, [...$pending, 'nonce' => $nonce], 600);
            Cookie::queue(Cookie::make(self::APPLE_STATE, $state, 10, '/auth/apple', null, true, true, false, 'none'));

            return $this->driver('apple', stateless: true, with: ['state' => $state, 'response_mode' => 'form_post'], scopes: ['name', 'email'], nonce: $nonce)->redirect();
        }

        // Event Schedule (M10): its own OAuth with PKCE, the verifier kept in this session.
        if ($provider === 'event-schedule') {
            if (! $this->eventSchedule->configured()) {
                return self::back($intended, 'unavailable');
            }

            $state = Str::random(40);
            $verifier = Str::random(64);
            $request->session()->put('auth.pending', $pending);
            $request->session()->put('auth.event_schedule', ['state' => $state, 'verifier' => $verifier]);

            return redirect()->away($this->eventSchedule->signInUrl($state, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')));
        }

        $request->session()->put('auth.pending', $pending);

        return $this->driver('google')->redirect();
    }

    /**
     * Back from Event Schedule: the state must be this browser's; who it is comes from ES's
     * /api/me with the token just given (which is then revoked: signing in keeps no connection).
     * Matched on the ES account's id only, never by email (the address isn't taken from ES).
     */
    public function eventScheduleCallback(Request $request): RedirectResponse
    {
        /** @var array{intended: string, link: bool, linkUser: int|null, ref: string|null, invite?: string|null, early?: string|null} $pending */
        $pending = (array) $request->session()->pull('auth.pending', ['intended' => '/', 'link' => false, 'linkUser' => null, 'ref' => null, 'invite' => null]);
        $started = (array) $request->session()->pull('auth.event_schedule', []);
        $state = (string) $request->query('state', '');

        if ($request->filled('error')) {
            return self::back($pending['intended'], 'cancelled');
        }

        if (! isset($started['state'], $started['verifier']) || ! hash_equals((string) $started['state'], $state) || ! is_string($code = $request->query('code'))) {
            return self::back($pending['intended'], 'expired');
        }

        try {
            $tokens = $this->eventSchedule->exchangeCode($code, (string) $started['verifier'], HttpEventSchedule::signInRedirectUri());
            $probe = (new EsConnection)->forceFill(['mode' => EsConnection::OAUTH, 'base_url' => rtrim((string) config('services.event_schedule.url'), '/'), 'access_token' => $tokens['accessToken'], 'refresh_token' => $tokens['refreshToken'], 'status' => EsConnection::ACTIVE, 'user_id' => 0]);
            $me = $this->eventSchedule->me($probe);
            $this->eventSchedule->revoke($probe);
        } catch (EventScheduleError $e) {
            return self::back($pending['intended'], $e->reason === EventScheduleError::CONNECT_EXPIRED ? 'expired' : 'failed');
        }

        if ($me['id'] === '') {
            return self::back($pending['intended'], 'failed');
        }

        return $this->finish($request, 'event_schedule', $me['id'], $me['name'], null, $pending);
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        /** @var array{intended: string, link: bool, linkUser: int|null, ref: string|null, invite?: string|null, early?: string|null} $pending */
        $pending = (array) $request->session()->pull('auth.pending', ['intended' => '/signin', 'link' => false, 'linkUser' => null, 'ref' => null, 'invite' => null]);

        if ($request->filled('error')) {
            return self::back($pending['intended'], 'cancelled');
        }

        try {
            $google = $this->driver('google')->user();
        } catch (InvalidStateException) {
            return self::back($pending['intended'], 'expired');
        } catch (Throwable $e) {
            report($e);

            return self::back($pending['intended'], 'failed');
        }

        $raw = $google instanceof AbstractUser ? (array) $google->getRaw() : [];

        if (! filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return self::back($pending['intended'], 'unverified');
        }

        return $this->finish($request, 'google', (string) $google->getId(), (string) $google->getName(), $google->getEmail(), $pending, verifiedEmail: strtolower((string) $google->getEmail()));
    }

    /**
     * Apple's cross-site POST: check the state, read who it is, and hand over to a same-site GET.
     */
    public function appleCallback(Request $request): RedirectResponse
    {
        $state = (string) $request->input('state');
        $cookie = $request->cookies->get(self::APPLE_STATE);
        /** @var array{intended: string, link: bool, linkUser: int|null, ref: string|null, invite?: string|null, early?: string|null, nonce?: string}|null $pending */
        $pending = $state !== '' && is_string($cookie) && hash_equals($cookie, $state) ? Cache::pull('auth:apple:'.$state) : null;

        if ($pending === null) {
            return self::back('/', 'expired');
        }

        if ($request->filled('error')) {
            return self::back($pending['intended'], 'cancelled');
        }

        try {
            $apple = $this->driver('apple', stateless: true, nonce: (string) ($pending['nonce'] ?? ''))->user();
        } catch (Throwable $e) {
            report($e);

            return self::back($pending['intended'], 'failed');
        }

        unset($pending['nonce']);
        $code = Str::random(40);
        // The state goes along, so only the browser that started (it holds the state cookie) can finish.
        // Apple's refresh token rides along encrypted, kept to revoke when the account is deleted.
        $refresh = $apple instanceof OAuthTwoUser && is_string($apple->refreshToken) && $apple->refreshToken !== '' ? Crypt::encryptString($apple->refreshToken) : null;
        Cache::put('auth:apple:complete:'.$code, ['id' => (string) $apple->getId(), 'name' => (string) $apple->getName(), 'email' => $apple->getEmail(), 'pending' => $pending, 'state' => $state, 'refresh' => $refresh], 120);

        return redirect()->route('auth.apple.complete', ['code' => $code]);
    }

    public function appleComplete(Request $request): RedirectResponse
    {
        /** @var array{id: string, name: string, email: string|null, pending: array{intended: string, link: bool, linkUser: int|null, ref: string|null, invite?: string|null, early?: string|null}, state: string, refresh?: string|null}|null $result */
        $result = Cache::pull('auth:apple:complete:'.$request->query('code'));
        $cookie = $request->cookies->get(self::APPLE_STATE);
        Cookie::queue(Cookie::forget(self::APPLE_STATE, '/auth/apple'));

        if ($result === null || ! is_string($cookie) || ! hash_equals($result['state'], $cookie)) {
            return self::back('/', 'expired');
        }

        return $this->finish($request, 'apple', $result['id'], $result['name'], $result['email'], $result['pending'], appleRefresh: isset($result['refresh']) ? Crypt::decryptString($result['refresh']) : null);
    }

    /**
     * @param  'google'|'apple'|'event_schedule'  $provider
     * @param  array{intended: string, link: bool, linkUser: int|null, ref: string|null, invite?: string|null, early?: string|null}  $pending
     */
    private function finish(Request $request, string $provider, string $id, string $name, ?string $email, array $pending, ?string $verifiedEmail = null, ?string $appleRefresh = null): RedirectResponse
    {
        $intended = $pending['intended'];
        $ip = (string) $request->ip();

        if (($block = $this->blocklist->find(email: $email, ip: $ip)) !== null) {
            $this->securityLog->record(SecurityEventType::Blocked, null, $ip, ['where' => $provider.'_sign_in', 'block' => $block->id]);

            return self::back($intended, 'blocked');
        }

        $current = $request->user();

        try {
            if ($pending['link']) {
                if (! $current instanceof User || $current->id !== $pending['linkUser'] || ! SignInSession::isRecent($request)) {
                    return self::back($intended, 'expired');
                }

                $this->signIn->link($current, $provider, $id);
                self::keepAppleToken($current, $appleRefresh);

                return redirect()->to(self::withQuery($intended, ['linked' => $provider]));
            }

            $invite = $pending['invite'] ?? null;
            $user = $this->signIn->handle($provider, $id, $name, $email, app()->getLocale(), $pending['ref'], $ip, AcceptInvite::opens($invite), $pending['early'] ?? null, Country::of($request));
            self::keepAppleToken($user, $appleRefresh);
            $this->signInSession->start($request, $user, $provider);

            // The invite this sign-in carried: Google or Apple proved who it is, so it's accepted
            // now (quietly not, if it can't be: the invite page says why).
            if ($invite !== null) {
                $user->forceFill(['pending_invite_hash' => hash('sha256', $invite)])->save();
                $this->acceptInvite->pending($user);
            }
        } catch (Refusal $refusal) {
            return self::back($intended, $refusal->refusalCode);
        }

        // Check 3 of AdminGate: the email Google itself confirmed during this sign-in.
        if ($verifiedEmail !== null) {
            $request->session()->put([AdminGate::SESSION_EMAIL => $verifiedEmail, AdminGate::SESSION_FRESH => true]);
        }

        if (str_starts_with($intended, '/admin')) {
            // A new admin, their email listed but not yet their Google ID: told the ID once, for
            // the list and app:admin:grant (it isn't a secret).
            if ($provider === 'google' && $this->awaitsGoogleId($id, $verifiedEmail)) {
                $request->session()->flash(self::YOUR_GOOGLE_ID, $id);

                return self::back($intended, 'not_admin');
            }

            $request->session()->put('url.intended', $intended);

            if ($this->secondFactor->required() && app(AdminGate::class)->isEditor($user, $request->session())) {
                return redirect()->route($this->secondFactor->isEnrolled($user) ? 'admin.second-factor.challenge' : 'admin.second-factor.setup');
            }

            return redirect()->to($intended);
        }

        return redirect()->to(self::withQuery($intended, ['signedIn' => $provider]));
    }

    /**
     * The web's Sign in with Apple: Apple's refresh token, revoked with the Services ID when the
     * account is deleted or Apple is removed (the apps keep theirs in apple_refresh_token).
     */
    private static function keepAppleToken(User $user, ?string $refresh): void
    {
        if ($refresh !== null) {
            $user->forceFill(['apple_web_refresh_token' => $refresh])->save();
        }
    }

    /**
     * @param  array<string, string>  $with
     * @param  list<string>  $scopes
     */
    private function driver(string $provider, bool $stateless = false, array $with = [], array $scopes = [], ?string $nonce = null): Provider
    {
        $driver = Socialite::driver($provider);

        // Socialite's test fake isn't an AbstractProvider and needs none of this.
        if ($driver instanceof AbstractProvider) {
            $driver->redirectUrl(url("/auth/{$provider}/callback"));

            if ($provider === 'google') {
                $with = ['prompt' => 'select_account', ...$with];
                $scopes = ['openid', 'email', 'profile', ...$scopes];
            }

            $driver->with($with)->scopes($scopes);

            if ($stateless) {
                $driver->stateless();
            }

            // Apple's id_token carries it, and the callback checks it (an empty one never matches).
            if ($nonce !== null && $driver instanceof AppleProvider) {
                $driver->setNonce($nonce);
            }
        }

        return $driver;
    }

    private function awaitsGoogleId(string $id, ?string $verifiedEmail): bool
    {
        $ids = [...(array) config('admin.admin_google_ids'), ...(array) config('admin.editor_google_ids')];
        $emails = [...(array) config('admin.admin_emails'), ...(array) config('admin.editor_emails')];

        return $verifiedEmail !== null && in_array($verifiedEmail, $emails, true) && ! in_array(strtolower($id), $ids, true);
    }

    /**
     * Back to the page the person came from, with why (also for a stale CSRF token on the form:
     * bootstrap/app.php), never to an error page outside the app.
     */
    public static function back(string $intended, string $reason): RedirectResponse
    {
        // The admin's sign-in page words its own errors; the app's pages read ?authError=.
        if (str_starts_with($intended, '/admin')) {
            return redirect()->route('admin.sign-in', ['intended' => $intended, 'error' => $reason]);
        }

        return redirect()->to(self::withQuery($intended, ['authError' => $reason]));
    }

    /**
     * @param  array<string, string>  $add
     */
    private static function withQuery(string $path, array $add): string
    {
        return $path.(str_contains($path, '?') ? '&' : '?').http_build_query($add);
    }
}
