<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Exceptions\Refusal;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The App Store Server API (docs/app-purchases-plan.md): a subscription's current state, from
 * Apple, for the environment its transaction came from. Sync needs it: what the app sends only
 * says which subscription, and Apple says what it is now (an old copy can't undo a refund).
 */
final class AppStoreApi
{
    private const HOSTS = ['Production' => 'https://api.storekit.itunes.apple.com', 'Sandbox' => 'https://api.storekit-sandbox.itunes.apple.com'];

    public function configured(): bool
    {
        return (string) config('services.app_store.issuer_id') !== '' && (string) config('services.app_store.key_id') !== '' && (string) config('services.app_store.private_key') !== '' && (string) config('services.apple.app_bundle_id') !== '';
    }

    /**
     * The latest transaction and renewal info of the subscription, each still signed (decode them
     * with AppleSignedData), and Apple's status for it (1 active, 2 expired, 3 billing retry, 4
     * grace period, 5 revoked). Null when Apple doesn't know it; a 503 when Apple doesn't answer
     * (the app's own copy is never trusted instead).
     *
     * @return array{status: int, transaction: string, renewal: string|null}|null
     */
    public function status(string $originalTransactionId, string $environment): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $answer = Http::withToken($this->token())->timeout(10)->get((self::HOSTS[$environment] ?? self::HOSTS['Production']).'/inApps/v1/subscriptions/'.rawurlencode($originalTransactionId))->throw()->json();
        } catch (RequestException $e) {
            if (in_array($e->response->status(), [400, 404], true)) {
                return null;
            }

            report($e);

            throw new Refusal(503, 'unavailable');
        } catch (Throwable $e) {
            report($e);

            throw new Refusal(503, 'unavailable');
        }

        foreach ((array) ($answer['data'] ?? []) as $group) {
            foreach ((array) ($group['lastTransactions'] ?? []) as $last) {
                if (($last['originalTransactionId'] ?? null) === $originalTransactionId && is_string($last['signedTransactionInfo'] ?? null)) {
                    return ['status' => (int) ($last['status'] ?? 0), 'transaction' => $last['signedTransactionInfo'], 'renewal' => is_string($last['signedRenewalInfo'] ?? null) ? $last['signedRenewalInfo'] : null];
                }
            }
        }

        return null;
    }

    private function token(): string
    {
        return JWT::encode([
            'iss' => (string) config('services.app_store.issuer_id'),
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinutes(20)->getTimestamp(),
            'aud' => 'appstoreconnect-v1',
            'bid' => (string) config('services.apple.app_bundle_id'),
        ], str_replace('\n', "\n", (string) config('services.app_store.private_key')), 'ES256', (string) config('services.app_store.key_id'));
    }
}
