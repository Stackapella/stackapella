<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Exceptions\Refusal;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The Google Play Developer API (docs/app-purchases-plan.md): a subscription purchase's state, and
 * its acknowledgement (Google refunds one that isn't acknowledged within three days). Through the
 * service account's key; off until it's set.
 */
final class PlayApi
{
    private const BASE = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/';

    public function configured(): bool
    {
        return (string) config('services.google_play.package') !== '' && $this->account() !== null;
    }

    /**
     * purchases.subscriptionsv2.get: the purchase as Google has it, or null when Google doesn't
     * know the token (it's not ours, or not real). A Google that doesn't answer is 503.
     *
     * @return array<string, mixed>|null
     */
    public function subscription(string $purchaseToken): ?array
    {
        try {
            return (array) Http::withToken($this->accessToken())->timeout(10)->get(self::BASE.rawurlencode((string) config('services.google_play.package')).'/purchases/subscriptionsv2/tokens/'.rawurlencode($purchaseToken))->throw()->json();
        } catch (RequestException $e) {
            if (in_array($e->response->status(), [400, 404, 410], true)) {
                return null;
            }

            report($e);

            throw new Refusal(503, 'unavailable');
        } catch (Throwable $e) {
            report($e);

            throw new Refusal(503, 'unavailable');
        }
    }

    public function acknowledge(string $subscriptionId, string $purchaseToken): bool
    {
        try {
            Http::withToken($this->accessToken())->timeout(10)->post(self::BASE.rawurlencode((string) config('services.google_play.package')).'/purchases/subscriptions/'.rawurlencode($subscriptionId).'/tokens/'.rawurlencode($purchaseToken).':acknowledge', (object) [])->throw();

            return true;
        } catch (Throwable $e) {
            // Retried at the next sync or notification, well within Google's three days.
            report($e);

            return false;
        }
    }

    /**
     * The service account's access token, from its key (Google's OAuth for servers), cached.
     */
    private function accessToken(): string
    {
        $account = $this->account() ?? throw new Refusal(423, 'paused');

        return (string) Cache::remember('google-play:access-token', 3000, static function () use ($account): string {
            $uri = (string) ($account['token_uri'] ?? 'https://oauth2.googleapis.com/token');
            $assertion = JWT::encode([
                'iss' => (string) $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/androidpublisher',
                'aud' => $uri,
                'iat' => now()->getTimestamp(),
                'exp' => now()->addHour()->getTimestamp(),
            ], (string) $account['private_key'], 'RS256');

            return (string) Http::asForm()->timeout(10)->post($uri, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion])->throw()->json('access_token');
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function account(): ?array
    {
        $json = json_decode((string) config('services.google_play.service_account'), true);

        return is_array($json) && is_string($json['client_email'] ?? null) && is_string($json['private_key'] ?? null) ? $json : null;
    }
}
