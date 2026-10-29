<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Auth\ProviderTokens;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * The stores, faked: an Apple-shaped certificate chain (a root we pin in place of Apple's, an
 * intermediate and a leaf with Apple's markers) that signs transactions and notifications as the
 * App Store does, and a Google service account, Play Developer API and Pub/Sub push token.
 */
trait StoreFakes
{
    /** @var array{rootPath: string, leafKey: \OpenSSLAsymmetricKey, x5c: list<string>}|null */
    private ?array $apple = null;

    /** @var array<string, array<string, mixed>> original transaction id => the latest claims signed for it */
    private array $appleLatest = [];

    /** @var array<string, array<string, mixed>> original transaction id => the latest renewal info signed for it */
    private array $appleRenewals = [];

    /** When true, Apple's Server API answers 500. */
    private bool $appleDown = false;

    /** @var array<string, array<string, mixed>> purchase token => subscriptionsv2 */
    private array $playSubscriptions = [];

    private string $googleKey = '';

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function appleSigned(array $claims): string
    {
        $this->apple ??= $this->appleChain();

        return JWT::encode($claims, $this->apple['leafKey'], 'ES256', null, ['x5c' => $this->apple['x5c']]);
    }

    /**
     * A signed transaction for an App Store subscription, as StoreKit 2 hands it to the app.
     *
     * @param  array<string, mixed>  $with
     */
    protected function appleTransaction(string $original, array $with = []): string
    {
        $claims = [
            'transactionId' => $original.'1',
            'originalTransactionId' => $original,
            'bundleId' => 'com.stackapella.app',
            'productId' => 'com.stackapella.plus.year',
            'type' => 'Auto-Renewable Subscription',
            'purchaseDate' => now()->getTimestampMs(),
            'expiresDate' => now()->addYear()->getTimestampMs(),
            'environment' => 'Production',
            'signedDate' => now()->getTimestampMs(),
            ...$with,
        ];
        // Apple's Server API answers with the latest it has signed.
        $this->appleLatest[$original] = $claims;

        return $this->appleSigned($claims);
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>  $with
     */
    protected function appleNotification(string $type, array $transaction, ?string $subtype = null, array $renewal = [], array $with = []): string
    {
        $this->appleRenewals[(string) $transaction['originalTransactionId']] = $renewal;

        return $this->appleSigned([
            'notificationType' => $type,
            ...($subtype !== null ? ['subtype' => $subtype] : []),
            'notificationUUID' => uniqid('n', true),
            'signedDate' => now()->getTimestampMs(),
            'data' => [
                'bundleId' => 'com.stackapella.app',
                'environment' => 'Production',
                'signedTransactionInfo' => $this->appleTransaction((string) $transaction['originalTransactionId'], $transaction),
                ...($renewal !== [] ? ['signedRenewalInfo' => $this->appleSigned(['originalTransactionId' => $transaction['originalTransactionId'], 'signedDate' => now()->getTimestampMs(), ...$renewal])] : []),
            ],
            ...$with,
        ]);
    }

    /**
     * The App Store: our chain pinned as Apple's, the Server API key set, and the API answering
     * a subscription's status from the latest transaction signed for it.
     */
    protected function fakeAppStore(): void
    {
        $this->apple ??= $this->appleChain();
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384]);
        openssl_pkey_export($key, $pem);
        config([
            'services.apple.app_bundle_id' => 'com.stackapella.app',
            'services.app_store.root_certificate' => $this->apple['rootPath'],
            'services.app_store.issuer_id' => 'issuer',
            'services.app_store.key_id' => 'KEY1',
            'services.app_store.private_key' => $pem,
        ]);

        Http::fake(['api.storekit*.itunes.apple.com/*' => function ($request) {
            if ($this->appleDown) {
                return Http::response([], 500);
            }

            $original = rawurldecode((string) preg_replace('#^.*/subscriptions/#', '', $request->url()));
            $claims = $this->appleLatest[$original] ?? null;

            if ($claims === null) {
                return Http::response(['errorCode' => 4040010], 404);
            }

            $renewal = ['autoRenewStatus' => 1, ...($this->appleRenewals[$original] ?? [])];
            $expired = (int) ($claims['expiresDate'] ?? 0) < now()->getTimestampMs();
            $status = match (true) {
                isset($claims['revocationDate']) => 5,
                $expired && (int) ($renewal['gracePeriodExpiresDate'] ?? 0) > now()->getTimestampMs() => 4,
                $expired => 2,
                default => 1,
            };

            return Http::response(['data' => [['subscriptionGroupIdentifier' => 'plus', 'lastTransactions' => [['originalTransactionId' => $original, 'status' => $status, 'signedTransactionInfo' => $this->appleSigned([...$claims, 'signedDate' => now()->getTimestampMs()]), 'signedRenewalInfo' => $this->appleSigned(['originalTransactionId' => $original, ...$renewal, 'signedDate' => now()->getTimestampMs()])]]]]]);
        }]);
    }

    /**
     * Google Play, with the service account set up, its OAuth, the Play Developer API answering
     * for the purchases given, and acknowledgements recorded; Pub/Sub's push signed by a key Google
     * "publishes".
     */
    protected function fakePlay(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->googleKey = $pem;
        $details = (array) openssl_pkey_get_details($key);
        $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        config(['services.google_play' => [
            'package' => 'com.stackapella.app',
            'service_account' => json_encode(['client_email' => 'play@stackapella.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']),
            'subscription' => 'plus',
            'base_plans' => ['month' => 'month', 'year' => 'year'],
            'push_audience' => 'https://app.stackapella.com/webhooks/google-play',
            'push_email' => 'pubsub@stackapella.iam.gserviceaccount.com',
        ]]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            ProviderTokens::GOOGLE_KEYS => Http::response(['keys' => [['kty' => 'RSA', 'kid' => 'g1', 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]]),
            'androidpublisher.googleapis.com/*' => function ($request) {
                if (str_contains($request->url(), ':acknowledge')) {
                    return Http::response([]);
                }

                $token = rawurldecode((string) preg_replace('#^.*/tokens/#', '', $request->url()));

                return isset($this->playSubscriptions[$token]) ? Http::response($this->playSubscriptions[$token]) : Http::response(['error' => ['code' => 404]], 404);
            },
        ]);
    }

    /**
     * What purchases.subscriptionsv2.get answers for a token.
     *
     * @param  array<string, mixed>  $with
     */
    protected function playPurchase(string $token, ?string $account, array $with = []): void
    {
        $this->playSubscriptions[$token] = [
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING',
            'lineItems' => [['productId' => 'plus', 'expiryTime' => now()->addMonth()->utc()->toIso8601ZuluString(), 'autoRenewingPlan' => ['autoRenewEnabled' => true], 'offerDetails' => ['basePlanId' => 'month']]],
            ...($account !== null ? ['externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $account]] : []),
            ...$with,
        ];
    }

    /**
     * Pub/Sub's push of a real-time developer notification, with Google's token for it.
     *
     * @param  array<string, mixed>  $notification
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    protected function playPush(array $notification, ?string $audience = null): array
    {
        $token = JWT::encode(['iss' => 'https://accounts.google.com', 'aud' => $audience ?? 'https://app.stackapella.com/webhooks/google-play', 'email' => 'pubsub@stackapella.iam.gserviceaccount.com', 'email_verified' => true, 'iat' => time(), 'exp' => time() + 3600], $this->googleKey, 'RS256', 'g1');

        return [['message' => ['data' => base64_encode((string) json_encode(['packageName' => 'com.stackapella.app', ...$notification])), 'messageId' => uniqid()], 'subscription' => 'projects/p/subscriptions/play'], ['Authorization' => 'Bearer '.$token]];
    }

    /**
     * @return array{rootPath: string, leafKey: \OpenSSLAsymmetricKey, x5c: list<string>}
     */
    private function appleChain(): array
    {
        $config = tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents((string) $config, "[req]\ndistinguished_name = dn\n[dn]\n[root]\nbasicConstraints = critical,CA:TRUE\nkeyUsage = keyCertSign\n[intermediate]\nbasicConstraints = critical,CA:TRUE\nkeyUsage = keyCertSign\n1.2.840.113635.100.6.2.1 = ASN1:NULL\n[leaf]\nbasicConstraints = CA:FALSE\n1.2.840.113635.100.6.11.1 = ASN1:NULL\n");
        $ec = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384, 'config' => $config];
        $make = static function (string $name, string $section, mixed $issuer, mixed $issuerKey) use ($ec, $config): array {
            $key = openssl_pkey_new($ec) ?: throw new \RuntimeException('No key.');
            $csr = openssl_csr_new(['commonName' => $name], $key, ['digest_alg' => 'sha256', 'config' => $config]) ?: throw new \RuntimeException('No CSR.');
            $cert = openssl_csr_sign($csr, $issuer, $issuerKey ?? $key, 365, ['digest_alg' => 'sha256', 'config' => $config, 'x509_extensions' => $section], random_int(1, PHP_INT_MAX)) ?: throw new \RuntimeException('No certificate.');
            openssl_x509_export($cert, $pem);

            return [$key, $cert, $pem];
        };

        [$rootKey, $root, $rootPem] = $make('Test Root', 'root', null, null);
        [$intermediateKey, $intermediate, $intermediatePem] = $make('Test WWDR', 'intermediate', $root, $rootKey);
        [$leafKey, , $leafPem] = $make('Test Store Signing', 'leaf', $intermediate, $intermediateKey);
        $der = static fn (string $pem): string => (string) preg_replace('/-----[^-]+-----|\s/', '', $pem);
        $rootPath = (string) tempnam(sys_get_temp_dir(), 'root');
        file_put_contents($rootPath, $rootPem);
        @unlink((string) $config);

        return ['rootPath' => $rootPath, 'leafKey' => $leafKey, 'x5c' => [$der($leafPem), $der($intermediatePem), $der($rootPem)]];
    }
}
