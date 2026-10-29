<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Exceptions\Refusal;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Apple's signed data (a transaction, its renewal info, a notification): a JWS whose `x5c` chain
 * must lead to Apple Root CA - G3, pinned in the repo, through certificates carrying Apple's own
 * markers (the App Store's receipt-signing leaf, the WWDR intermediate), and whose signature is
 * the leaf's. Only then is the payload trusted (Apple's App Store Server Library does the same).
 */
final class AppleSignedData
{
    private const LEAF_OID = '1.2.840.113635.100.6.11.1';

    private const INTERMEDIATE_OID = '1.2.840.113635.100.6.2.1';

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $jws): array
    {
        try {
            $parts = explode('.', $jws);
            $header = count($parts) === 3 ? json_decode(self::base64url($parts[0]), true) : null;
            $chain = is_array($header) ? ($header['x5c'] ?? null) : null;

            if (! is_array($chain) || count($chain) < 3 || ($header['alg'] ?? null) !== 'ES256') {
                throw new Refusal(422, 'invalid_purchase');
            }

            [$leaf, $intermediate, $root] = array_map(static fn (mixed $der): string => "-----BEGIN CERTIFICATE-----\n".chunk_split((string) $der, 64, "\n")."-----END CERTIFICATE-----\n", array_slice($chain, 0, 3));
            $pinned = (string) file_get_contents((string) config('services.app_store.root_certificate'));

            $trusted = openssl_x509_fingerprint($root, 'sha256') === openssl_x509_fingerprint($pinned, 'sha256')
                && openssl_x509_verify($intermediate, $root) === 1
                && openssl_x509_verify($leaf, $intermediate) === 1
                && self::marked($leaf, self::LEAF_OID) && self::marked($intermediate, self::INTERMEDIATE_OID);

            if (! $trusted) {
                throw new Refusal(422, 'invalid_purchase');
            }

            $key = self::publicKey($leaf);
            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) json_encode(JWT::decode($jws, new Key($key, 'ES256'))), true);

            // The certificates were valid when Apple signed it.
            $at = is_numeric($payload['signedDate'] ?? null) ? intdiv((int) $payload['signedDate'], 1000) : time();

            foreach ([$leaf, $intermediate] as $certificate) {
                $info = (array) openssl_x509_parse($certificate);

                if ($at < (int) ($info['validFrom_time_t'] ?? PHP_INT_MAX) || $at > (int) ($info['validTo_time_t'] ?? 0)) {
                    throw new Refusal(422, 'invalid_purchase');
                }
            }

            return $payload;
        } catch (Refusal $refusal) {
            throw $refusal;
        } catch (Throwable) {
            throw new Refusal(422, 'invalid_purchase');
        }
    }

    private static function publicKey(string $certificate): string
    {
        $key = openssl_pkey_get_public($certificate);
        $details = $key !== false ? openssl_pkey_get_details($key) : false;

        if ($details === false || ! is_string($details['key'] ?? null)) {
            throw new Refusal(422, 'invalid_purchase');
        }

        return $details['key'];
    }

    private static function marked(string $certificate, string $oid): bool
    {
        return array_key_exists($oid, (array) (openssl_x509_parse($certificate)['extensions'] ?? []));
    }

    private static function base64url(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
