<?php

declare(strict_types=1);

namespace App\Services\EventSchedule;

use App\Models\EsConnection;

/**
 * Everything Stackapella asks of Event Schedule (contract/event-schedule.md), in our own words:
 * camelCase, times as ISO 8601 in UTC. The real one is HttpEventSchedule; FakeEventSchedule
 * stands in for tests and, locally, until ES's side is live. Failures throw EventScheduleError.
 *
 * @phpstan-type Booking array{id: string, eventId: string|null, status: string, startsAt: string, endsAt: string, timezone: string, manageUrl: string|null, icalUrl: string|null, locationUrl: string|null, externalId: string|null}
 * @phpstan-type Tokens array{accessToken: string, refreshToken: string|null, expiresIn: int|null, scopes: list<string>}
 */
interface EventSchedule
{
    public const SCOPES = ['profile', 'schedules:read', 'appointments:read', 'appointments:write', 'events:read', 'webhooks:manage', 'members:read'];

    /** Whether the server can connect anyone by OAuth (a client is set up, or it's the fake). */
    public function configured(): bool;

    public function isFake(): bool;

    public function authorizeUrl(string $state, string $challenge): string;

    /**
     * Sign in with Event Schedule (M10): the same OAuth, with the `profile` scope only, back to
     * /auth/event-schedule/callback.
     */
    public function signInUrl(string $state, string $challenge): string;

    /**
     * @return Tokens
     */
    public function exchangeCode(string $code, string $verifier, ?string $redirectUri = null): array;

    /**
     * The account a connection (saved or not yet) acts as.
     *
     * @return array{id: string, name: string}
     */
    public function me(EsConnection $connection): array;

    public function revoke(EsConnection $connection): void;

    /**
     * @return list<array{subdomain: string, name: string, type: string, timezone: string}>
     */
    public function schedules(EsConnection $connection): array;

    /**
     * @return list<array{id: string, name: string, durationMinutes: int, timezone: string, requiresApproval: bool, nextAvailable: string|null}>
     */
    public function appointmentTypes(EsConnection $connection, string $schedule): array;

    /**
     * The free start times, as ES has them.
     *
     * @return list<string>
     */
    public function slots(EsConnection $connection, string $typeId, string $from, int $days): array;

    /**
     * @param  array{externalId: string, slot: string, name: string, email: string, guestTimezone: string, notes: string|null}  $booking
     * @return Booking
     */
    public function book(EsConnection $connection, string $typeId, array $booking, string $idempotencyKey): array;

    /**
     * @return Booking|null
     */
    public function booking(EsConnection $connection, string $id): ?array;

    /**
     * @return Booking|null
     */
    public function bookingByExternalId(EsConnection $connection, string $externalId): ?array;

    /**
     * @return Booking
     */
    public function cancel(EsConnection $connection, string $id, ?string $reason): array;

    /**
     * @return array{id: string, secret: string}
     */
    public function registerWebhook(EsConnection $connection, string $url, string $schedule): array;

    public function deleteWebhook(EsConnection $connection, string $id): void;

    /**
     * @return array{members: list<array{id: string, name: string, email: string|null, role: string|null}>, next: string|null}
     */
    public function members(EsConnection $connection, string $schedule, ?string $cursor): array;

    /**
     * @return list<array{id: string, name: string, startsAt: string, endsAt: string|null, timezone: string, url: string|null}>
     */
    public function events(EsConnection $connection, string $schedule, string $from, string $to): array;
}
