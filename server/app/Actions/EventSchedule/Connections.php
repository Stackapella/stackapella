<?php

declare(strict_types=1);

namespace App\Actions\EventSchedule;

use App\Actions\Coaching\Lessons;
use App\Exceptions\Refusal;
use App\Models\CoachOffering;
use App\Models\EsConnection;
use App\Models\LessonBooking;
use App\Models\User;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\EventScheduleError;
use App\Support\Http\PublicAddress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connecting an account to Event Schedule (docs/event-schedule-integration-plan.md G1, G5): by
 * OAuth with PKCE (the state and verifier wait 10 minutes in the cache, for this person only), or a
 * selfhosted install's address and API key. Then a schedule is picked, which registers ES's
 * webhook for it. Disconnecting revokes the token; while booked lessons are still to come the
 * connection stays (tokenless) so their webhook keeps working.
 */
final class Connections
{
    private const PENDING_MINUTES = 10;

    public function __construct(private readonly EventSchedule $es) {}

    /**
     * @return array{url: string, expiresAt: string}
     */
    public function start(User $user): array
    {
        if (! $this->es->configured()) {
            throw new Refusal(423, 'paused');
        }

        $state = Str::random(40);
        $verifier = Str::random(64);
        Cache::put('es-connect:'.$state, ['user' => $user->id, 'verifier' => $verifier], now()->addMinutes(self::PENDING_MINUTES));

        return [
            'url' => $this->es->authorizeUrl($state, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')),
            'expiresAt' => now()->addMinutes(self::PENDING_MINUTES)->utc()->toIso8601ZuluString(),
        ];
    }

    public function finish(User $user, string $code, string $state): EsConnection
    {
        $pending = Cache::pull('es-connect:'.$state);

        if (! is_array($pending) || ($pending['user'] ?? null) !== $user->id) {
            throw new Refusal(410, 'connect_expired');
        }

        try {
            $tokens = $this->es->exchangeCode($code, (string) $pending['verifier']);
        } catch (EventScheduleError $e) {
            // Event Schedule was down: "try again later" has to work, so the sign-in waits again.
            if ($e->reason === EventScheduleError::UNAVAILABLE) {
                Cache::put('es-connect:'.$state, $pending, now()->addMinutes(self::PENDING_MINUTES));
            }

            throw $e;
        }

        return $this->save($user, [
            'mode' => EsConnection::OAUTH,
            'base_url' => rtrim((string) config('services.event_schedule.url'), '/'),
            'access_token' => $tokens['accessToken'],
            'refresh_token' => $tokens['refreshToken'],
            'expires_at' => $tokens['expiresIn'] !== null ? now()->addSeconds($tokens['expiresIn']) : null,
            'scopes' => $tokens['scopes'],
        ]);
    }

    public function withKey(User $user, string $baseUrl, string $apiKey): EsConnection
    {
        $baseUrl = rtrim($baseUrl, '/');

        if (! PublicAddress::allowed($baseUrl)) {
            throw ValidationException::withMessages(['baseUrl' => __('validation.es_address')]);
        }

        try {
            return $this->save($user, ['mode' => EsConnection::API_KEY, 'base_url' => $baseUrl, 'access_token' => $apiKey, 'refresh_token' => null, 'expires_at' => null, 'scopes' => null]);
        } catch (EventScheduleError $e) {
            throw ValidationException::withMessages($e->reason === EventScheduleError::UNAVAILABLE ? ['baseUrl' => __('validation.es_unreachable')] : ['apiKey' => __('validation.es_key')]);
        }
    }

    /**
     * Pick the schedule for lessons: its webhook is registered (and an old one's removed).
     */
    public function pickSchedule(User $user, string $subdomain): EsConnection
    {
        $connection = $this->usable($user);
        $schedule = collect($this->es->schedules($connection))->firstWhere('subdomain', $subdomain);

        if ($schedule === null) {
            throw ValidationException::withMessages(['schedule' => __('validation.in', ['attribute' => 'schedule'])]);
        }

        // Lessons still to come in the current schedule keep its webhook: no moving away from it.
        if ($connection->schedule !== null && $connection->schedule !== $subdomain && self::hasUpcoming($connection)) {
            throw new Refusal(409, 'lessons_to_come');
        }

        if ($connection->schedule !== $subdomain || $connection->webhook_id === null) {
            $this->dropWebhook($connection);
            $hook = $this->es->registerWebhook($connection, route('webhooks.event-schedule', ['connection' => $connection->sqid()]), $subdomain);

            if ($hook['id'] === '' || $hook['secret'] === '') {
                throw EventScheduleError::unavailable('Event Schedule registered no webhook.');
            }

            $connection->forceFill(['webhook_id' => $hook['id'], 'webhook_secret' => $hook['secret'], 'webhook_failing_at' => null]);
        }

        $connection->forceFill(['schedule' => $subdomain, 'schedule_name' => $schedule['name'], 'schedule_timezone' => $schedule['timezone']])->save();

        return $connection;
    }

    public function disconnect(User $user): void
    {
        $connection = $this->mine($user);
        $upcoming = self::hasUpcoming($connection);

        if (! $upcoming && $connection->usable()) {
            $this->dropWebhook($connection);
        }

        if ($connection->usable()) {
            $this->es->revoke($connection);
        }

        if ($upcoming) {
            $connection->forceFill(['status' => EsConnection::DISCONNECTED, 'access_token' => null, 'refresh_token' => null, 'expires_at' => null, 'disconnected_at' => now()])->save();

            return;
        }

        $connection->delete();
    }

    /**
     * An account being deleted: its webhook goes and its token is revoked at ES, as far as ES
     * answers (the row itself goes with the account).
     */
    public function forget(User $user): void
    {
        $connection = EsConnection::query()->where('user_id', $user->id)->first();

        if ($connection === null || ! $connection->usable()) {
            return;
        }

        $this->dropWebhook($connection);
        $this->es->revoke($connection);
    }

    /**
     * The person's connection, still connected (a disconnected one kept for its lessons isn't).
     */
    public function mine(User $user): EsConnection
    {
        return EsConnection::query()->where('user_id', $user->id)->where('status', '!=', EsConnection::DISCONNECTED)->first() ?? throw new Refusal(404, 'not_found');
    }

    /**
     * The person's connection, working: `409 reconnect_required` when ES stopped accepting it.
     */
    public function usable(User $user): EsConnection
    {
        $connection = $this->mine($user);

        if (! $connection->usable()) {
            throw new Refusal(409, 'reconnect_required');
        }

        return $connection;
    }

    /**
     * The connection as the API shows it (contract/schemas/eventSchedule.connection.json).
     *
     * @return array<string, mixed>
     */
    public static function view(EsConnection $connection): array
    {
        return [
            'status' => $connection->status,
            'mode' => $connection->mode,
            'baseUrl' => $connection->base_url,
            'account' => ['id' => $connection->es_user_id, 'name' => $connection->es_user_name],
            'schedule' => $connection->schedule !== null ? ['subdomain' => $connection->schedule, 'name' => (string) $connection->schedule_name, 'timezone' => (string) ($connection->schedule_timezone ?? 'UTC')] : null,
            'webhook' => $connection->webhook_id === null ? 'none' : ($connection->webhook_failing_at !== null ? 'failing' : 'active'),
            'connectedAt' => $connection->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * Check the credentials with ES (on a copy, so a refused one never touches the saved
     * connection), then keep them. Reconnecting the same ES account keeps the schedule and its
     * webhook; another account starts without one.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function save(User $user, array $credentials): EsConnection
    {
        $probe = (new EsConnection)->forceFill([...$credentials, 'user_id' => $user->id, 'status' => EsConnection::ACTIVE]);
        $me = $this->es->me($probe);

        return DB::transaction(function () use ($user, $credentials, $me): EsConnection {
            $connection = EsConnection::query()->where('user_id', $user->id)->lockForUpdate()->first() ?? (new EsConnection)->forceFill(['user_id' => $user->id]);
            $sameAccount = $connection->exists && $connection->es_user_id === $me['id'] && $connection->base_url === $credentials['base_url'];

            // Another ES account while booked lessons are still to come on this one: their
            // webhook and bookings live there, so it waits until they're past.
            if ($connection->exists && ! $sameAccount && self::hasUpcoming($connection)) {
                throw new Refusal(409, 'lessons_to_come');
            }

            $connection->forceFill([
                ...$credentials,
                'es_user_id' => $me['id'],
                'es_user_name' => mb_substr($me['name'], 0, 190),
                'status' => EsConnection::ACTIVE,
                'failing_since' => null,
                'last_error' => null,
                'disconnected_at' => null,
                ...($sameAccount ? [] : ['schedule' => null, 'schedule_name' => null, 'schedule_timezone' => null, 'webhook_id' => null, 'webhook_secret' => null, 'webhook_failing_at' => null]),
            ])->save();

            return $connection;
        });
    }

    /**
     * The person's connection, still connected, or null (connection.show).
     */
    public function current(User $user): ?EsConnection
    {
        return EsConnection::query()->where('user_id', $user->id)->where('status', '!=', EsConnection::DISCONNECTED)->first();
    }

    /**
     * The picked schedule's appointment types, each with the lesson already using it.
     *
     * @return list<array<string, mixed>>
     */
    public function types(User $user): array
    {
        $connection = $this->usable($user);

        if ($connection->schedule === null) {
            return [];
        }

        $linked = CoachOffering::query()->where('coach_id', $user->id)->where('kind', CoachOffering::LESSON)->where('es_schedule', $connection->schedule)->whereNull('archived_at')->whereNotNull('es_appointment_type_id')->get()->keyBy('es_appointment_type_id');

        return array_map(static fn (array $type): array => [
            ...$type,
            'linked' => ($offering = $linked->get($type['id'])) !== null ? ['id' => $offering->sqid(), 'title' => $offering->title] : null,
        ], $this->es->appointmentTypes($connection, $connection->schedule));
    }

    private static function hasUpcoming(EsConnection $connection): bool
    {
        return Lessons::upcoming(LessonBooking::query()->where('es_connection_id', $connection->id))->exists();
    }

    private function dropWebhook(EsConnection $connection): void
    {
        if ($connection->webhook_id === null) {
            return;
        }

        try {
            $this->es->deleteWebhook($connection, $connection->webhook_id);
        } catch (EventScheduleError $e) {
            // Left at ES, it only sends to a secret we no longer hold: refused there, harmless.
            report($e);
        }

        $connection->forceFill(['webhook_id' => null, 'webhook_secret' => null]);
    }
}
