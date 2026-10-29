<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\Refusal;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The native apps' Google and Apple sign-ins (the token-auth plan): the ID token the device got is
 * checked here (the provider's published keys, the issuer, the audience, the expiry), and Apple's
 * authorisation code is exchanged for its refresh token, kept to revoke when the account is
 * deleted (App Store Guideline 5.1.1(v)). With firebase/php-jwt, which Sign in with Apple's
 * provider already brings. A nonce ties each token to the app's own sign-in (Apple's per sign-in,
 * Google's per launch, as its SDK allows). Each ID token signs in once (a copy replayed is refused), and a key id
 * the cached keys don't know fetches them again, at most once a minute (the providers rotate).
 */
final class ProviderTokens
{
    public const GOOGLE_KEYS = 'https://www.googleapis.com/oauth2/v3/certs';

    private const GOOGLE_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public const APPLE_KEYS = 'https://appleid.apple.com/auth/keys';

    private const APPLE_ISSUER = 'https://appleid.apple.com';

    /**
     * @return array{id: string, email: string|null, name: string}
     */
    public function google(string $idToken, ?string $nonce = null): array
    {
        $claims = $this->decode($idToken, self::GOOGLE_KEYS);

        // Optional for Google (its SDK binds one per launch), but a token that carries one must match.
        if (($nonce !== null || isset($claims['nonce'])) && ! hash_equals((string) ($claims['nonce'] ?? ''), (string) $nonce)) {
            throw new Refusal(401, 'invalid_token');
        }
        $audiences = (array) config('services.google.app_client_ids');

        if (! in_array($claims['iss'] ?? null, self::GOOGLE_ISSUERS, true) || ! in_array($claims['aud'] ?? null, $audiences, true) || ! is_string($claims['sub'] ?? null)) {
            throw new Refusal(401, 'invalid_token');
        }

        // Google's address must be one Google itself confirmed (as on the web).
        if (($claims['email_verified'] ?? false) !== true && ($claims['email_verified'] ?? null) !== 'true') {
            throw new Refusal(403, 'unverified');
        }

        self::once($idToken, $claims);

        return ['id' => $claims['sub'], 'email' => is_string($claims['email'] ?? null) ? $claims['email'] : null, 'name' => is_string($claims['name'] ?? null) ? $claims['name'] : ''];
    }

    /**
     * @return array{id: string, email: string|null}
     */
    public function apple(string $identityToken, string $nonce): array
    {
        $claims = $this->decode($identityToken, self::APPLE_KEYS);

        // The app gave Apple this sign-in's nonce as its SHA-256 (hex); the token carries it back.
        if (! hash_equals((string) ($claims['nonce'] ?? ''), hash('sha256', $nonce))) {
            throw new Refusal(401, 'invalid_token');
        }
        $bundle = (string) config('services.apple.app_bundle_id');

        if (($claims['iss'] ?? null) !== self::APPLE_ISSUER || $bundle === '' || ($claims['aud'] ?? null) !== $bundle || ! is_string($claims['sub'] ?? null)) {
            throw new Refusal(401, 'invalid_token');
        }

        self::once($identityToken, $claims);

        return ['id' => $claims['sub'], 'email' => is_string($claims['email'] ?? null) ? $claims['email'] : null];
    }

    /**
     * Google Play's real-time notifications arrive by Pub/Sub push, with a token Google signed for
     * our address (the audience) as the push's service account.
     */
    public function googlePush(string $jwt): bool
    {
        try {
            $claims = $this->decode($jwt, self::GOOGLE_KEYS);
        } catch (Refusal) {
            return false;
        }

        return in_array($claims['iss'] ?? null, self::GOOGLE_ISSUERS, true)
            && (string) config('services.google_play.push_audience') !== '' && ($claims['aud'] ?? null) === (string) config('services.google_play.push_audience')
            && (string) config('services.google_play.push_email') !== '' && ($claims['email'] ?? null) === (string) config('services.google_play.push_email')
            && ($claims['email_verified'] ?? false) === true;
    }

    /**
     * Apple's refresh token for the code the device got, when the code is the same person's as
     * the identity token (null when Apple won't give one, or it's someone else's: the sign-in
     * still stands, there is just nothing to revoke later).
     */
    public function appleRefreshToken(string $code, string $sub): ?string
    {
        try {
            $answer = Http::asForm()->timeout(10)->post(self::APPLE_ISSUER.'/auth/token', [
                'client_id' => (string) config('services.apple.app_bundle_id'),
                'client_secret' => $this->appleSecret((string) config('services.apple.app_bundle_id')),
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $token = $answer->json('refresh_token');
        $idToken = $answer->json('id_token');

        if (! $answer->successful() || ! is_string($token) || ! is_string($idToken)) {
            return null;
        }

        try {
            $claims = $this->decode($idToken, self::APPLE_KEYS);
        } catch (Refusal) {
            return null;
        }

        return ($claims['sub'] ?? null) === $sub && ($claims['aud'] ?? null) === (string) config('services.apple.app_bundle_id') ? $token : null;
    }

    /**
     * The account is going, or Apple is no longer a way in: Apple is told to revoke what it gave
     * us, the apps' token with the bundle id and the web's with the Services ID, and both are
     * forgotten.
     */
    public function revokeAppleFor(User $user): void
    {
        $tokens = [
            [$user->apple_refresh_token, (string) config('services.apple.app_bundle_id')],
            [$user->apple_web_refresh_token, (string) config('services.apple.client_id')],
        ];

        foreach ($tokens as [$token, $client]) {
            if ($token !== null && $client !== '') {
                $this->revokeApple($token, $client);
            }
        }

        if ($user->apple_refresh_token !== null || $user->apple_web_refresh_token !== null) {
            $user->forceFill(['apple_refresh_token' => null, 'apple_web_refresh_token' => null])->save();
        }
    }

    private function revokeApple(string $refreshToken, string $client): void
    {
        try {
            Http::asForm()->timeout(10)->post(self::APPLE_ISSUER.'/auth/revoke', [
                'client_id' => $client,
                'client_secret' => $this->appleSecret($client),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ])->throw();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $token, string $keysUrl): array
    {
        try {
            $keys = self::keys($keysUrl, false);
            $kid = self::kid($token);

            if ($kid !== null && ! in_array($kid, array_column((array) ($keys['keys'] ?? []), 'kid'), true) && Cache::add('provider-keys-refetched:'.$keysUrl, true, 60)) {
                $keys = self::keys($keysUrl, true);
            }

            // A minute either way for our clock against Google's or Apple's (a fresh token's iat).
            $leeway = JWT::$leeway;
            JWT::$leeway = 60;

            try {
                return (array) JWT::decode($token, JWK::parseKeySet($keys, 'RS256'));
            } finally {
                JWT::$leeway = $leeway;
            }
        } catch (Throwable) {
            throw new Refusal(401, 'invalid_token');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function keys(string $url, bool $fresh): array
    {
        $key = 'provider-keys:'.$url;

        if ($fresh) {
            Cache::forget($key);
        }

        /** @var array<string, mixed> */
        return Cache::remember($key, 3600, fn (): array => (array) Http::timeout(10)->get($url)->throw()->json());
    }

    private static function kid(string $token): ?string
    {
        $header = json_decode((string) base64_decode(strtr(explode('.', $token)[0], '-_', '+/'), true), true);

        return is_array($header) && is_string($header['kid'] ?? null) ? $header['kid'] : null;
    }

    /**
     * An ID token signs in once: a copy (from a log, or taken on the way) is refused until it
     * expires.
     *
     * @param  array<string, mixed>  $claims
     */
    private static function once(string $token, array $claims): void
    {
        $seconds = max(60, (int) ($claims['exp'] ?? 0) - now()->getTimestamp() + 60);

        if (! Cache::add('provider-token-used:'.hash('sha256', $token), true, $seconds)) {
            throw new Refusal(401, 'invalid_token');
        }
    }

    /**
     * The client secret Apple asks for, for one client (the apps' bundle id or the web's Services
     * ID): a short-lived ES256 token signed with our key.
     */
    private function appleSecret(string $client): string
    {
        return JWT::encode([
            'iss' => (string) config('services.apple.team_id'),
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'aud' => self::APPLE_ISSUER,
            'sub' => $client,
        ], str_replace('\n', "\n", (string) config('services.apple.private_key')), 'ES256', (string) config('services.apple.key_id'));
    }
}
