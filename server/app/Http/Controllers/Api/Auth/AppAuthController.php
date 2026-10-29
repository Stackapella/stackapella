<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Beta\AcceptInvite;
use App\Actions\Users\SignInWithPassword;
use App\Actions\Users\SignInWithProvider;
use App\Actions\Users\VerifyEmail;
use App\Enums\SecurityEventType;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateAppDevice;
use App\Http\Requests\Auth\App\AppAppleRequest;
use App\Http\Requests\Auth\App\AppGoogleRequest;
use App\Http\Requests\Auth\App\AppRefreshRequest;
use App\Http\Requests\Auth\App\AppSignInRequest;
use App\Http\Requests\Auth\App\AppVerifyRequest;
use App\Http\Resources\AccountResource;
use App\Models\AppDevice;
use App\Models\User;
use App\Services\Auth\ProviderTokens;
use App\Support\Accounts\AppTokens;
use App\Support\Accounts\SignInSession;
use App\Support\Trust\Blocklist;
use App\Support\Trust\Country;
use App\Support\Trust\SecurityLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * auth.app.* (contract/api.md, "The apps: device tokens"): the native apps sign in with the same
 * accounts and rules as the web (blocks, suspension, paused sign-ups, the age question), and get a
 * device's tokens instead of a session.
 */
final class AppAuthController extends Controller
{
    public function __construct(private readonly AppTokens $tokens, private readonly Blocklist $blocklist, private readonly SecurityLog $securityLog) {}

    public function signIn(AppSignInRequest $request, SignInWithPassword $signIn): JsonResponse
    {
        $this->ensureNotBlocked((string) $request->validated('email'), $request, 'app_sign_in');
        $user = $signIn->handle((string) $request->validated('email'), (string) $request->validated('password'), $request->ip());

        return $this->signedIn($request, $user, 'password');
    }

    public function google(AppGoogleRequest $request, ProviderTokens $providers, SignInWithProvider $signIn, AcceptInvite $acceptInvite): JsonResponse
    {
        $nonce = $request->validated('nonce');
        $google = $providers->google((string) $request->validated('idToken'), is_string($nonce) ? $nonce : null);
        $this->ensureNotBlocked($google['email'], $request, 'app_google_sign_in');

        if ($request->boolean('link')) {
            return $this->link($request, $signIn, 'google', $google['id']);
        }

        $invite = $request->validated('invite');
        $early = $request->validated('early');
        $user = $signIn->handle('google', $google['id'], $google['name'], $google['email'], app()->getLocale(), null, $request->ip(), AcceptInvite::opens(is_string($invite) ? $invite : null), is_string($early) ? $early : null, Country::of($request));
        $this->acceptCarried($user, is_string($invite) ? $invite : null, $acceptInvite);

        return $this->signedIn($request, $user, 'google');
    }

    public function apple(AppAppleRequest $request, ProviderTokens $providers, SignInWithProvider $signIn, AcceptInvite $acceptInvite): JsonResponse
    {
        $apple = $providers->apple((string) $request->validated('identityToken'), (string) $request->validated('nonce'));
        $this->ensureNotBlocked($apple['email'], $request, 'app_apple_sign_in');

        if ($request->boolean('link')) {
            $linked = $this->link($request, $signIn, 'apple', $apple['id']);
            $linker = $request->user();
            $this->keepAppleToken($providers, $request, $linker instanceof User ? $linker : null, $apple['id']);

            return $linked;
        }

        $invite = $request->validated('invite');
        $early = $request->validated('early');
        $user = $signIn->handle('apple', $apple['id'], (string) ($request->validated('name') ?? ''), $apple['email'], app()->getLocale(), null, $request->ip(), AcceptInvite::opens(is_string($invite) ? $invite : null), is_string($early) ? $early : null, Country::of($request));
        $this->acceptCarried($user, is_string($invite) ? $invite : null, $acceptInvite);

        $this->keepAppleToken($providers, $request, $user, $apple['id']);

        return $this->signedIn($request, $user, 'apple');
    }

    public function verify(AppVerifyRequest $request, VerifyEmail $verify, AcceptInvite $acceptInvite): JsonResponse
    {
        $user = $verify->handle((string) $request->validated('token'));
        // The beta invite the sign-up carried, now that the address is proven.
        $acceptInvite->pending($user);

        return $this->signedIn($request, $user, 'verify');
    }

    public function refresh(AppRefreshRequest $request): JsonResponse
    {
        $issued = $this->tokens->refresh((string) $request->validated('refreshToken'), $request->ip());

        return response()->json(['data' => self::tokens($issued)]);
    }

    public function signOut(Request $request): Response
    {
        $device = $request->attributes->get(AuthenticateAppDevice::DEVICE);

        if ($device instanceof AppDevice) {
            $device->forceFill(['revoked_at' => now()])->save();
        }

        return response()->noContent();
    }

    /**
     * Adding Google or Apple to the signed-in account from the app's Settings: a recent sign-in,
     * and never one that's another account's (as the web's link=1).
     *
     * @param  'google'|'apple'  $provider
     */
    private function link(FormRequest $request, SignInWithProvider $signIn, string $provider, string $id): JsonResponse
    {
        $user = $request->user();
        $device = $request->attributes->get(AuthenticateAppDevice::DEVICE);

        if (! $user instanceof User || ! $device instanceof AppDevice) {
            // Past the access token's hour, the app refreshes and tries again: it isn't signed out.
            throw new Refusal(401, $request->attributes->get(AuthenticateAppDevice::REFUSED) === 'token_expired' ? 'token_expired' : 'unauthenticated');
        }

        if (! SignInSession::isRecent($request)) {
            throw new Refusal(403, 'recent_sign_in_required');
        }

        $signIn->link($user, $provider, $id);

        return $this->signedIn($request, $user->refresh(), $device->method, $device, relink: true);
    }

    private function signedIn(FormRequest $request, User $user, string $method, ?AppDevice $own = null, bool $relink = false): JsonResponse
    {
        /** @var array{name: string, platform: string, model?: string|null, appVersion?: string|null, replace?: string|null} $device */
        $device = (array) $request->validated('device', []);
        // `replace` signs in again on the app's own device only when the request holds that
        // device's access token (even past its hour); any other id is a new device, with its email.
        $own ??= AppTokens::owner((string) $request->bearerToken());
        $device['replace'] = $own !== null && ($relink || ($device['replace'] ?? null) === $own->sqid()) ? $own->sqid() : null;

        $issued = $this->tokens->issue($user, $device, $method, $request->ip(), keepSignedInAt: $relink);
        Country::remember($user, $request);

        return response()->json(['data' => [...self::tokens($issued), 'account' => (new AccountResource($user->refresh()))->toArray($request)]]);
    }

    /**
     * @param  array{accessToken: string, accessExpiresAt: string, refreshToken: string, device: AppDevice}  $issued
     * @return array<string, mixed>
     */
    private static function tokens(array $issued): array
    {
        return [
            'accessToken' => $issued['accessToken'],
            'accessExpiresAt' => $issued['accessExpiresAt'],
            'refreshToken' => $issued['refreshToken'],
            'device' => AppTokens::view($issued['device'], $issued['device']),
        ];
    }

    /**
     * Apple's refresh token, kept to revoke with Apple when the account is deleted (App Store
     * Guideline 5.1.1(v)), on a sign-in and when Apple is linked.
     */
    private function keepAppleToken(ProviderTokens $providers, AppAppleRequest $request, ?User $user, string $sub): void
    {
        $refresh = $user !== null ? $providers->appleRefreshToken((string) $request->validated('authorizationCode'), $sub) : null;

        if ($user !== null && $refresh !== null) {
            $user->forceFill(['apple_refresh_token' => $refresh])->save();
        }
    }

    private function acceptCarried(User $user, ?string $invite, AcceptInvite $acceptInvite): void
    {
        if ($invite !== null && $invite !== '') {
            $user->forceFill(['pending_invite_hash' => hash('sha256', $invite)])->save();
            $acceptInvite->pending($user);
        }
    }

    private function ensureNotBlocked(?string $email, Request $request, string $where): void
    {
        if (($block = $this->blocklist->find(email: $email, ip: $request->ip())) !== null) {
            $this->securityLog->record(SecurityEventType::Blocked, null, $request->ip(), ['where' => $where, 'block' => $block->id]);

            throw new Refusal(403, 'blocked');
        }
    }
}
