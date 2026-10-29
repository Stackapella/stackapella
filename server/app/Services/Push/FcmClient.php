<?php

declare(strict_types=1);

namespace App\Services\Push;

use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Firebase Cloud Messaging's HTTP v1 API, with a service account allowed to send (config/services.php,
 * `fcm`). A push is to one registration token.
 */
final class FcmClient
{
    public function configured(): bool
    {
        return (string) config('services.fcm.project_id') !== '' && $this->account() !== null;
    }

    /**
     * @param  array{title: string, body: string, type: string, url: string}  $push
     * @return ApnsClient::SENT|ApnsClient::GONE|ApnsClient::FAILED
     */
    public function send(string $token, array $push, Carbon $expires): string
    {
        $ttl = max(0, (int) now()->diffInSeconds($expires, false));
        $message = [
            'token' => $token,
            'notification' => ['title' => $push['title'], 'body' => $push['body']],
            'data' => ['type' => $push['type'], 'url' => $push['url']],
            'android' => ['ttl' => $ttl.'s', 'collapse_key' => $push['type'], 'notification' => ['tag' => $push['type']]],
        ];

        try {
            $answer = Http::withToken($this->accessToken())->timeout(10)
                ->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode((string) config('services.fcm.project_id')).'/messages:send', ['message' => $message]);
        } catch (Throwable $e) {
            report($e);

            return ApnsClient::FAILED;
        }

        $code = (string) $answer->json('error.details.0.errorCode', $answer->json('error.status', ''));

        if ($answer->successful()) {
            return ApnsClient::SENT;
        }

        if ($answer->status() === 404 || $code === 'UNREGISTERED' || ($code === 'INVALID_ARGUMENT' && str_contains((string) $answer->json('error.message'), 'token'))) {
            return ApnsClient::GONE;
        }

        if ($answer->status() === 401) {
            Cache::forget('fcm:access-token');
        }

        report(new \RuntimeException("FCM refused a push ({$answer->status()} {$code})."));

        return ApnsClient::FAILED;
    }

    /**
     * The service account's access token for sending (Google's OAuth for servers), cached.
     */
    private function accessToken(): string
    {
        $account = $this->account() ?? throw new \RuntimeException('FCM has no service account.');

        return (string) Cache::remember('fcm:access-token', 3000, static function () use ($account): string {
            $uri = (string) ($account['token_uri'] ?? 'https://oauth2.googleapis.com/token');
            $assertion = JWT::encode([
                'iss' => (string) $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
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
        $json = json_decode((string) config('services.fcm.service_account'), true);

        return is_array($json) && is_string($json['client_email'] ?? null) && is_string($json['private_key'] ?? null) ? $json : null;
    }
}
