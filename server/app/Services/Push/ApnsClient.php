<?php

declare(strict_types=1);

namespace App\Services\Push;

use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Apple Push Notification service, by its HTTP/2 API with a token-based key (config/services.php,
 * `apns`). A push is to one device token, at the environment the app registered it for.
 */
final class ApnsClient
{
    public const SENT = 'sent';

    /** The token is no longer the app's (uninstalled, or another environment's): forget it. */
    public const GONE = 'gone';

    public const FAILED = 'failed';

    private const HOSTS = ['production' => 'https://api.push.apple.com', 'sandbox' => 'https://api.sandbox.push.apple.com'];

    public function configured(): bool
    {
        return (string) config('services.apns.key_id') !== '' && (string) config('services.apns.team_id') !== '' && (string) config('services.apns.private_key') !== '' && (string) config('services.apns.topic') !== '';
    }

    /**
     * @param  array{title: string, body: string, type: string, url: string}  $push
     * @return self::SENT|self::GONE|self::FAILED
     */
    public function send(string $token, string $environment, array $push, Carbon $expires): string
    {
        $payload = ['aps' => ['alert' => ['title' => $push['title'], 'body' => $push['body']], 'sound' => 'default', 'thread-id' => $push['type']], 'type' => $push['type'], 'url' => $push['url']];

        try {
            $answer = Http::withToken($this->token())->withOptions(['version' => 2.0])->timeout(10)
                ->withHeaders([
                    'apns-topic' => (string) config('services.apns.topic'),
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                    'apns-expiration' => (string) $expires->getTimestamp(),
                ])
                ->post((self::HOSTS[$environment] ?? self::HOSTS['production']).'/3/device/'.rawurlencode($token), $payload);
        } catch (Throwable $e) {
            report($e);

            return self::FAILED;
        }

        $reason = (string) $answer->json('reason');

        return match (true) {
            $answer->successful() => self::SENT,
            $answer->status() === 410, in_array($reason, ['BadDeviceToken', 'DeviceTokenNotForTopic', 'Unregistered'], true) => self::GONE,
            default => $this->failed($answer->status(), $reason),
        };
    }

    /**
     * @return self::FAILED
     */
    private function failed(int $status, string $reason): string
    {
        // Our key's token went stale early: the next push makes a new one.
        if ($reason === 'ExpiredProviderToken' || $reason === 'InvalidProviderToken') {
            Cache::forget('apns:provider-token');
        }

        report(new \RuntimeException("APNs refused a push ({$status} {$reason})."));

        return self::FAILED;
    }

    /**
     * The provider token (an ES256 JWT of the key), which Apple wants renewed at most every 20
     * minutes and at least every hour.
     */
    private function token(): string
    {
        return (string) Cache::remember('apns:provider-token', 1800, static fn (): string => JWT::encode(
            ['iss' => (string) config('services.apns.team_id'), 'iat' => now()->getTimestamp()],
            (string) config('services.apns.private_key'),
            'ES256',
            (string) config('services.apns.key_id'),
        ));
    }
}
