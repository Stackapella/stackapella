<?php

declare(strict_types=1);

namespace App\Services\EventSchedule;

use App\Models\EsConnection;
use App\Support\Http\PublicAddress;
use App\Support\Http\ResponseCap;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Event Schedule over its API (contract/event-schedule.md; iaw's style: raw `Http`). The token is
 * refreshed before it expires, and once more on a 401; a refresh ES refuses marks the connection
 * `needs_reconnect`. Reads time out after 10 seconds and are tried 3 times; writes aren't retried
 * here (the caller retries with the same `Idempotency-Key`). Redirects are never followed.
 *
 * @phpstan-import-type Booking from EventSchedule
 * @phpstan-import-type Tokens from EventSchedule
 */
final class HttpEventSchedule implements EventSchedule
{
    public function configured(): bool
    {
        return (string) config('services.event_schedule.client_id') !== '' && (string) config('services.event_schedule.client_secret') !== '';
    }

    public function isFake(): bool
    {
        return false;
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return $this->hosted().'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('services.event_schedule.client_id'),
            'redirect_uri' => self::redirectUri(),
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function signInUrl(string $state, string $challenge): string
    {
        return $this->hosted().'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('services.event_schedule.client_id'),
            'redirect_uri' => self::signInRedirectUri(),
            'scope' => 'profile',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, string $verifier, ?string $redirectUri = null): array
    {
        $response = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri ?? self::redirectUri(), 'code_verifier' => $verifier]);

        if ($response->clientError()) {
            throw new EventScheduleError(EventScheduleError::CONNECT_EXPIRED, 'ES refused the sign-in code: '.$response->status());
        }

        return $this->tokens($response);
    }

    public function me(EsConnection $connection): array
    {
        $data = (array) $this->send($connection, 'GET', '/api/me')->json('data');

        return ['id' => (string) ($data['id'] ?? ''), 'name' => (string) ($data['name'] ?? '')];
    }

    public function revoke(EsConnection $connection): void
    {
        if ($connection->mode !== EsConnection::OAUTH || $connection->access_token === null) {
            return;
        }

        try {
            $this->token(['token' => $connection->refresh_token ?? $connection->access_token], '/oauth/revoke');
        } catch (EventScheduleError $e) {
            // The token stays valid at ES until it expires; nothing here uses it again.
            report($e);
        }
    }

    public function schedules(EsConnection $connection): array
    {
        return array_values(array_map(static fn (array $s): array => [
            'subdomain' => (string) ($s['subdomain'] ?? ''),
            'name' => (string) ($s['name'] ?? ''),
            'type' => (string) ($s['type'] ?? ''),
            'timezone' => (string) ($s['timezone'] ?? 'UTC'),
        ], $this->list($this->send($connection, 'GET', '/api/schedules'))));
    }

    public function appointmentTypes(EsConnection $connection, string $schedule): array
    {
        return array_values(array_map(static fn (array $t): array => [
            'id' => (string) ($t['id'] ?? ''),
            'name' => (string) ($t['name'] ?? ''),
            'durationMinutes' => (int) ($t['duration_minutes'] ?? 0),
            'timezone' => (string) ($t['timezone'] ?? 'UTC'),
            'requiresApproval' => (bool) ($t['requires_approval'] ?? false),
            'nextAvailable' => isset($t['next_available']) ? (string) $t['next_available'] : null,
        ], $this->list($this->send($connection, 'GET', '/api/schedules/'.rawurlencode($schedule).'/appointment-types'))));
    }

    public function slots(EsConnection $connection, string $typeId, string $from, int $days): array
    {
        $slots = $this->list($this->send($connection, 'GET', '/api/appointment-types/'.rawurlencode($typeId).'/slots', ['from' => $from, 'days' => $days]));

        return array_values(array_filter(array_map(static fn (array $slot): string => (string) ($slot['starts_at'] ?? ''), $slots)));
    }

    public function book(EsConnection $connection, string $typeId, array $booking, string $idempotencyKey): array
    {
        $response = $this->send($connection, 'POST', '/api/appointment-types/'.rawurlencode($typeId).'/bookings', [
            'slot' => $booking['slot'],
            'external_id' => $booking['externalId'],
            'payment' => 'external',
            'name' => $booking['name'],
            'email' => $booking['email'],
            'guest_timezone' => $booking['guestTimezone'],
            'notes' => $booking['notes'],
        ], $idempotencyKey);

        if ($response->status() === 409) {
            throw new EventScheduleError(EventScheduleError::SLOT_TAKEN);
        }

        return self::booked((array) $this->ok($response)->json('data'));
    }

    public function booking(EsConnection $connection, string $id): ?array
    {
        $response = $this->send($connection, 'GET', '/api/bookings/'.rawurlencode($id));

        return $response->status() === 404 ? null : self::booked((array) $this->ok($response)->json('data'));
    }

    public function bookingByExternalId(EsConnection $connection, string $externalId): ?array
    {
        $found = $this->list($this->send($connection, 'GET', '/api/bookings', ['external_id' => $externalId]));

        return $found === [] ? null : self::booked($found[0]);
    }

    public function cancel(EsConnection $connection, string $id, ?string $reason): array
    {
        $response = $this->send($connection, 'POST', '/api/bookings/'.rawurlencode($id).'/cancel', ['reason' => $reason], 'cancel-'.$id);

        // Already cancelled (by the other side, a moment before): it is as it is.
        if ($response->status() === 409) {
            return $this->booking($connection, $id) ?? throw new EventScheduleError(EventScheduleError::NOT_FOUND);
        }

        if ($response->status() === 404) {
            throw new EventScheduleError(EventScheduleError::NOT_FOUND);
        }

        return self::booked((array) $this->ok($response)->json('data'));
    }

    public function registerWebhook(EsConnection $connection, string $url, string $schedule): array
    {
        $data = (array) $this->ok($this->send($connection, 'POST', '/api/webhooks', ['url' => $url, 'events' => ['appointment.*'], 'schedule' => $schedule], 'webhook-'.$connection->id.'-'.$schedule.'-'.Str::random(12)))->json('data');

        return ['id' => (string) ($data['id'] ?? ''), 'secret' => (string) ($data['secret'] ?? '')];
    }

    public function deleteWebhook(EsConnection $connection, string $id): void
    {
        $response = $this->send($connection, 'DELETE', '/api/webhooks/'.rawurlencode($id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    public function members(EsConnection $connection, string $schedule, ?string $cursor): array
    {
        $response = $this->send($connection, 'GET', '/api/schedules/'.rawurlencode($schedule).'/members', array_filter(['cursor' => $cursor]));

        return [
            'members' => array_values(array_map(static fn (array $m): array => [
                'id' => (string) ($m['id'] ?? ''),
                'name' => (string) ($m['name'] ?? ''),
                'email' => isset($m['email']) ? (string) $m['email'] : null,
                'role' => isset($m['role']) ? (string) $m['role'] : null,
            ], $this->list($response))),
            'next' => is_string($next = $response->json('meta.next_cursor')) ? $next : null,
        ];
    }

    public function events(EsConnection $connection, string $schedule, string $from, string $to): array
    {
        return array_values(array_map(static fn (array $e): array => [
            'id' => (string) ($e['id'] ?? ''),
            'name' => (string) ($e['name'] ?? ''),
            'startsAt' => (string) ($e['starts_at'] ?? ''),
            'endsAt' => isset($e['ends_at']) ? (string) $e['ends_at'] : null,
            'timezone' => (string) ($e['timezone'] ?? 'UTC'),
            'url' => isset($e['url']) ? (string) $e['url'] : null,
        ], $this->list($this->send($connection, 'GET', '/api/events', ['schedule' => $schedule, 'from' => $from, 'to' => $to]))));
    }

    public static function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/connect/event-schedule/callback';
    }

    public static function signInRedirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/auth/event-schedule/callback';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(EsConnection $connection, string $method, string $path, array $data = [], ?string $idempotencyKey = null, bool $again = false): Response
    {
        if ($connection->access_token === null || ($connection->exists && $connection->status === EsConnection::NEEDS_RECONNECT)) {
            throw new EventScheduleError(EventScheduleError::RECONNECT);
        }

        // A selfhosted address is checked again each time (where it points can change), and the
        // connection is pinned to the address checked.
        $pin = null;

        if ($connection->mode === EsConnection::API_KEY) {
            $pin = PublicAddress::resolve($connection->base_url);

            if ($pin === false) {
                throw new EventScheduleError(EventScheduleError::RECONNECT, 'The selfhosted address is no longer public.');
            }
        }

        if ($connection->exists && $connection->mode === EsConnection::OAUTH && $connection->expires_at !== null && $connection->expires_at->lt(now()->addMinute())) {
            $this->refresh($connection, (string) $connection->access_token);
        }

        $request = $this->client($connection->base_url, $method === 'GET', $pin !== null && $pin['ip'] !== null ? $pin : null);
        $request = $connection->mode === EsConnection::API_KEY ? $request->withHeaders(['X-API-Key' => (string) $connection->access_token]) : $request->withToken((string) $connection->access_token);

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $request->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException|RequestException $e) {
            throw EventScheduleError::unavailable("Event Schedule didn't answer {$method} {$path}.", $e);
        }

        if ($response->status() === 401 && $connection->exists && $connection->mode === EsConnection::OAUTH && ! $again) {
            $this->refresh($connection, (string) $connection->access_token, force: true);

            return $this->send($connection, $method, $path, $data, $idempotencyKey, again: true);
        }

        if ($response->status() === 401 || ($response->status() === 403 && $response->json('error') === 'insufficient_scope')) {
            $this->needsReconnect($connection, $response->status() === 401 ? 'unauthorized' : 'insufficient_scope');

            throw new EventScheduleError(EventScheduleError::RECONNECT);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw EventScheduleError::unavailable("Event Schedule answered {$response->status()} to {$method} {$path}.");
        }

        return $response;
    }

    /**
     * A new access token, once even when several requests find it expired together (ES rotates
     * the refresh token, so a second refresh with the old one would fail).
     */
    private function refresh(EsConnection $connection, string $staleToken, bool $force = false): void
    {
        try {
            Cache::lock('es-refresh-'.$connection->id, 20)->block(15, function () use ($connection, $staleToken, $force): void {
                $connection->refresh();

                if ($connection->access_token !== $staleToken || (! $force && $connection->expires_at?->gt(now()->addMinute()))) {
                    return;
                }

                if ($connection->refresh_token === null) {
                    $this->needsReconnect($connection, 'no_refresh_token');

                    throw new EventScheduleError(EventScheduleError::RECONNECT);
                }

                $response = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token]);

                if ($response->clientError()) {
                    $this->needsReconnect($connection, (string) ($response->json('error') ?? 'refresh_refused'));

                    throw new EventScheduleError(EventScheduleError::RECONNECT);
                }

                $tokens = $this->tokens($response);
                $connection->forceFill([
                    'access_token' => $tokens['accessToken'],
                    'refresh_token' => $tokens['refreshToken'] ?? $connection->refresh_token,
                    'expires_at' => $tokens['expiresIn'] !== null ? now()->addSeconds($tokens['expiresIn']) : null,
                    'scopes' => $tokens['scopes'] !== [] ? $tokens['scopes'] : $connection->scopes,
                    'failing_since' => null,
                    'last_error' => null,
                ])->save();
            });
        } catch (EventScheduleError $e) {
            throw $e;
        } catch (Throwable $e) {
            throw EventScheduleError::unavailable('Refreshing an Event Schedule token failed.', $e);
        }
    }

    private function needsReconnect(EsConnection $connection, string $error): void
    {
        if ($connection->exists) {
            $connection->forceFill(['status' => EsConnection::NEEDS_RECONNECT, 'failing_since' => $connection->failing_since ?? now(), 'last_error' => mb_substr($error, 0, 190)])->save();
        }
    }

    /**
     * @param  array<string, string>  $form
     */
    private function token(array $form, string $path = '/oauth/token'): Response
    {
        try {
            $response = $this->client($this->hosted(), retry: false)->asForm()->post($path, [
                ...$form,
                'client_id' => (string) config('services.event_schedule.client_id'),
                'client_secret' => (string) config('services.event_schedule.client_secret'),
            ]);
        } catch (ConnectionException|RequestException $e) {
            throw EventScheduleError::unavailable('Event Schedule\'s token endpoint didn\'t answer.', $e);
        }

        if ($response->serverError()) {
            throw EventScheduleError::unavailable("Event Schedule's token endpoint answered {$response->status()}.");
        }

        return $response;
    }

    /**
     * @return Tokens
     */
    private function tokens(Response $response): array
    {
        $access = $response->json('access_token');

        if (! is_string($access) || $access === '') {
            throw EventScheduleError::unavailable('Event Schedule sent no access token.');
        }

        return [
            'accessToken' => $access,
            'refreshToken' => is_string($refresh = $response->json('refresh_token')) ? $refresh : null,
            'expiresIn' => is_numeric($expires = $response->json('expires_in')) ? (int) $expires : null,
            'scopes' => array_values(array_filter(explode(' ', (string) $response->json('scope')))),
        ];
    }

    /**
     * @param  array{host: string, port: int, ip: string|null}|null  $pin
     */
    private function client(string $baseUrl, bool $retry, ?array $pin = null): PendingRequest
    {
        // A self-hosted install is someone else's server: its answers are capped in size.
        $options = ['allow_redirects' => false, ...ResponseCap::options()];

        if ($pin !== null) {
            $options['curl'] = [CURLOPT_RESOLVE => ["{$pin['host']}:{$pin['port']}:{$pin['ip']}"]];
        }

        $request = Http::baseUrl(rtrim($baseUrl, '/'))->acceptJson()->timeout($retry ? 10 : 15)->connectTimeout(5)->withOptions($options);

        return $retry ? $request->retry(3, 200, static fn (Throwable $e): bool => $e instanceof ConnectionException || ($e instanceof RequestException && $e->response->serverError()), throw: false) : $request;
    }

    private function ok(Response $response): Response
    {
        if (! $response->successful()) {
            throw EventScheduleError::unavailable("Event Schedule refused: {$response->status()} ".mb_substr($response->body(), 0, 300));
        }

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(Response $response): array
    {
        $data = $this->ok($response)->json('data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    private function hosted(): string
    {
        return rtrim((string) config('services.event_schedule.url'), '/');
    }

    /**
     * @param  array<string, mixed>  $b
     * @return Booking
     */
    public static function booked(array $b): array
    {
        return [
            'id' => (string) ($b['id'] ?? ''),
            'eventId' => isset($b['event_id']) ? (string) $b['event_id'] : null,
            'status' => (string) ($b['status'] ?? 'confirmed'),
            'startsAt' => (string) ($b['starts_at'] ?? ''),
            'endsAt' => (string) ($b['ends_at'] ?? ''),
            'timezone' => (string) ($b['timezone'] ?? 'UTC'),
            'manageUrl' => isset($b['manage_url']) ? (string) $b['manage_url'] : null,
            'icalUrl' => isset($b['ical_url']) ? (string) $b['ical_url'] : null,
            'locationUrl' => isset($b['location_url']) ? (string) $b['location_url'] : null,
            'externalId' => isset($b['external_id']) ? (string) $b['external_id'] : null,
        ];
    }
}
