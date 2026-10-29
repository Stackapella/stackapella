<?php

declare(strict_types=1);

namespace App\Services\EventSchedule;

use App\Models\EsConnection;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Event Schedule as contract/event-schedule.md says it behaves, with nothing leaving the server:
 * for the tests, and locally (`EVENT_SCHEDULE_FAKE=true`) until ES's side is live; never in
 * production. Its sign-in comes straight back to /connect/event-schedule/callback. Two schedules,
 * two appointment types (one needs approval), free times at 10:00, 14:00 and 18:00 London time,
 * weekly Thursday rehearsals and a concert. What it holds is kept in the cache, so it lasts
 * between requests locally.
 *
 * Tests steer it: `failNext()` makes the next call fail as ES would, `take()` makes a time go.
 * Every fake Sign in with Event Schedule is the same Event Schedule person.
 *
 * @phpstan-import-type Booking from EventSchedule
 *
 * @phpstan-type State array{bookings: array<string, array<string, string|null>>, webhooks: array<string, array{url: string, schedule: string, secret: string}>, taken: list<string>, revoked: list<string>, fail: list<string>, calls: list<string>}
 */
final class FakeEventSchedule implements EventSchedule
{
    private const KEY = 'fake-event-schedule';

    public const TYPES = [
        'Ap45' => ['name' => '45-minute lesson', 'durationMinutes' => 45, 'requiresApproval' => false],
        'Ap20' => ['name' => 'Trial lesson', 'durationMinutes' => 20, 'requiresApproval' => true],
    ];

    public function configured(): bool
    {
        return true;
    }

    public function isFake(): bool
    {
        return true;
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return HttpEventSchedule::redirectUri().'?'.http_build_query(['code' => 'fake-'.$challenge, 'state' => $state]);
    }

    public function signInUrl(string $state, string $challenge): string
    {
        return HttpEventSchedule::signInRedirectUri().'?'.http_build_query(['code' => 'fake-'.$challenge, 'state' => $state]);
    }

    public function exchangeCode(string $code, string $verifier, ?string $redirectUri = null): array
    {
        $this->step('exchangeCode');

        // As ES checks PKCE: the code was made from the challenge of this verifier.
        if ($code !== 'fake-'.rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')) {
            throw new EventScheduleError(EventScheduleError::CONNECT_EXPIRED);
        }

        return ['accessToken' => 'fake-at-'.Str::random(16), 'refreshToken' => 'fake-rt-'.Str::random(16), 'expiresIn' => 3600, 'scopes' => self::SCOPES];
    }

    public function me(EsConnection $connection): array
    {
        $this->step('me', $connection);

        if ($connection->mode === EsConnection::API_KEY && ! str_starts_with((string) $connection->access_token, 'es-')) {
            throw new EventScheduleError(EventScheduleError::RECONNECT);
        }

        return ['id' => 'es-'.substr(hash('sha256', (string) $connection->user_id), 0, 8), 'name' => (string) (User::query()->whereKey($connection->user_id)->value('name') ?? 'Event Schedule user')];
    }

    public function revoke(EsConnection $connection): void
    {
        $this->step('revoke', $connection);
        $this->change(function (array &$state) use ($connection): void {
            $state['revoked'][] = (string) $connection->access_token;
        });
    }

    public function schedules(EsConnection $connection): array
    {
        $this->step('schedules', $connection);

        return [
            ['subdomain' => 'grace-sings', 'name' => 'Grace Sings', 'type' => 'talent', 'timezone' => 'Europe/London'],
            ['subdomain' => 'riverside-choir', 'name' => 'Riverside Choir', 'type' => 'talent', 'timezone' => 'Europe/London'],
        ];
    }

    public function appointmentTypes(EsConnection $connection, string $schedule): array
    {
        $this->step('appointmentTypes', $connection);
        $types = [];

        foreach (self::TYPES as $id => $type) {
            $next = $this->slots($connection, $id, now()->toDateString(), 14)[0] ?? null;
            $types[] = ['id' => $id, ...$type, 'timezone' => 'Europe/London', 'nextAvailable' => $next];
        }

        return $types;
    }

    public function slots(EsConnection $connection, string $typeId, string $from, int $days): array
    {
        $this->step('slots', $connection);

        if (! isset(self::TYPES[$typeId])) {
            throw new EventScheduleError(EventScheduleError::NOT_FOUND);
        }

        $state = $this->state();
        $busy = [...$state['taken'], ...array_map(static fn (array $b): string => (string) $b['startsAt'], array_filter($state['bookings'], static fn (array $b): bool => $b['typeId'] === $typeId && $b['status'] !== 'cancelled'))];
        $slots = [];
        $day = Carbon::parse($from, 'Europe/London')->startOfDay();

        for ($i = 0; $i < $days; $i++, $day->addDay()) {
            foreach ([10, 14, 18] as $hour) {
                $start = $day->copy()->setTime($hour, 0)->utc();

                if ($start->gt(now()->addHours(2)) && ! in_array($start->toIso8601ZuluString(), $busy, true)) {
                    $slots[] = $start->toIso8601ZuluString();
                }
            }
        }

        return $slots;
    }

    public function book(EsConnection $connection, string $typeId, array $booking, string $idempotencyKey): array
    {
        $this->step('book', $connection);
        $state = $this->state();

        foreach ($state['bookings'] as $existing) {
            if ($existing['externalId'] === $booking['externalId']) {
                return $this->public($existing);
            }
        }

        if (! in_array($booking['slot'], $this->slots($connection, $typeId, Carbon::parse($booking['slot'])->subDay()->toDateString(), 3), true)) {
            throw new EventScheduleError(EventScheduleError::SLOT_TAKEN);
        }

        $id = 'Bk'.Str::random(8);
        $start = Carbon::parse($booking['slot'])->utc();
        $made = [
            'id' => $id,
            'eventId' => 'Ev'.Str::random(6),
            'status' => self::TYPES[$typeId]['requiresApproval'] ? 'pending_approval' : 'confirmed',
            'startsAt' => $start->toIso8601ZuluString(),
            'endsAt' => $start->copy()->addMinutes(self::TYPES[$typeId]['durationMinutes'])->toIso8601ZuluString(),
            'timezone' => 'Europe/London',
            'manageUrl' => 'https://eventschedule.com/manage/'.$id.'?token='.Str::random(12),
            'icalUrl' => 'https://eventschedule.com/ical/'.$id.'.ics',
            'locationUrl' => 'https://meet.example.com/'.strtolower($id),
            'externalId' => $booking['externalId'],
            'typeId' => $typeId,
            'name' => $booking['name'],
            'email' => $booking['email'],
        ];
        $this->change(function (array &$state) use ($id, $made): void {
            $state['bookings'][$id] = $made;
        });

        return $this->public($made);
    }

    public function booking(EsConnection $connection, string $id): ?array
    {
        $this->step('booking', $connection);
        $found = $this->state()['bookings'][$id] ?? null;

        return $found !== null ? $this->public($found) : null;
    }

    public function bookingByExternalId(EsConnection $connection, string $externalId): ?array
    {
        $this->step('bookingByExternalId', $connection);

        foreach ($this->state()['bookings'] as $booking) {
            if ($booking['externalId'] === $externalId) {
                return $this->public($booking);
            }
        }

        return null;
    }

    public function cancel(EsConnection $connection, string $id, ?string $reason): array
    {
        $this->step('cancel', $connection);

        if (! isset($this->state()['bookings'][$id])) {
            throw new EventScheduleError(EventScheduleError::NOT_FOUND);
        }

        return $this->public($this->set($id, ['status' => 'cancelled']));
    }

    public function registerWebhook(EsConnection $connection, string $url, string $schedule): array
    {
        $this->step('registerWebhook', $connection);
        $hook = ['id' => 'Wh'.Str::random(6), 'secret' => 'fake-whsec-'.Str::random(24)];
        $this->change(function (array &$state) use ($hook, $url, $schedule): void {
            $state['webhooks'][$hook['id']] = ['url' => $url, 'schedule' => $schedule, 'secret' => $hook['secret']];
        });

        return $hook;
    }

    public function deleteWebhook(EsConnection $connection, string $id): void
    {
        $this->step('deleteWebhook', $connection);
        $this->change(function (array &$state) use ($id): void {
            unset($state['webhooks'][$id]);
        });
    }

    public function members(EsConnection $connection, string $schedule, ?string $cursor): array
    {
        $this->step('members', $connection);

        return ['members' => [
            ['id' => 'Mb1', 'name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'role' => 'member'],
            ['id' => 'Mb2', 'name' => 'Ben Cole', 'email' => 'ben@example.com', 'role' => 'member'],
            ['id' => 'Mb3', 'name' => 'Chloe Park', 'email' => null, 'role' => 'admin'],
        ], 'next' => null];
    }

    public function events(EsConnection $connection, string $schedule, string $from, string $to): array
    {
        $this->step('events', $connection);
        $events = [];
        $day = Carbon::parse($from, 'Europe/London')->next(Carbon::THURSDAY);
        $until = Carbon::parse($to, 'Europe/London');

        for (; $day->lte($until) && count($events) < 8; $day->addWeek()) {
            $start = $day->copy()->setTime(19, 0)->utc();
            $events[] = ['id' => 'Ev'.$start->format('Ymd'), 'name' => 'Thursday rehearsal', 'startsAt' => $start->toIso8601ZuluString(), 'endsAt' => $start->copy()->addHours(2)->toIso8601ZuluString(), 'timezone' => 'Europe/London', 'url' => 'https://'.$schedule.'.eventschedule.com/rehearsal-'.$start->format('Ymd')];
        }

        $concert = Carbon::parse($from, 'Europe/London')->addWeeks(5)->next(Carbon::SATURDAY)->setTime(18, 30)->utc();

        if ($concert->lte($until)) {
            $events[] = ['id' => 'Ev'.$concert->format('Ymd').'c', 'name' => 'Winter concert', 'startsAt' => $concert->toIso8601ZuluString(), 'endsAt' => null, 'timezone' => 'Europe/London', 'url' => 'https://'.$schedule.'.eventschedule.com/winter-concert'];
        }

        usort($events, static fn (array $a, array $b): int => strcmp($a['startsAt'], $b['startsAt']));

        return $events;
    }

    /**
     * The next call fails as ES would: `event_schedule_unavailable`, `reconnect_required`,
     * `slot_taken` or `not_found`. Several queue up, in order.
     */
    public function failNext(string $reason): void
    {
        $this->change(function (array &$state) use ($reason): void {
            $state['fail'][] = $reason;
        });
    }

    /** Somebody else books this start time, as if on ES's own page. */
    public function take(string $startsAt): void
    {
        $this->change(function (array &$state) use ($startsAt): void {
            $state['taken'][] = Carbon::parse($startsAt)->utc()->toIso8601ZuluString();
        });
    }

    /**
     * Change a booking as the coach or the singer would in ES (a test then delivers the webhook).
     *
     * @param  array<string, string>  $changes
     * @return Booking
     */
    public function set(string $id, array $changes): array
    {
        $booking = null;
        $this->change(function (array &$state) use ($id, $changes, &$booking): void {
            $state['bookings'][$id] = [...$state['bookings'][$id], ...$changes];
            $booking = $state['bookings'][$id];
        });

        return $this->public($booking ?? []);
    }

    /**
     * @return State
     */
    public function state(): array
    {
        /** @var State $state */
        $state = [...['bookings' => [], 'webhooks' => [], 'taken' => [], 'revoked' => [], 'fail' => [], 'calls' => []], ...(array) Cache::get(self::KEY, [])];

        return $state;
    }

    /**
     * @param  callable(State): void  $change
     */
    private function change(callable $change): void
    {
        Cache::lock(self::KEY.'-lock', 5)->block(5, function () use ($change): void {
            $state = $this->state();
            $change($state);
            Cache::put(self::KEY, $state, now()->addDays(30));
        });
    }

    private function step(string $call, ?EsConnection $connection = null): void
    {
        if ($connection !== null && $connection->exists && $connection->status === EsConnection::NEEDS_RECONNECT) {
            throw new EventScheduleError(EventScheduleError::RECONNECT);
        }

        $fail = null;
        $this->change(function (array &$state) use ($call, &$fail): void {
            $state['calls'][] = $call;
            $fail = array_shift($state['fail']);
        });

        if ($fail !== null) {
            if ($fail === EventScheduleError::RECONNECT && $connection !== null && $connection->exists) {
                $connection->forceFill(['status' => EsConnection::NEEDS_RECONNECT, 'failing_since' => now(), 'last_error' => 'refresh_refused'])->save();
            }

            throw new EventScheduleError($fail);
        }
    }

    /**
     * @param  array<string, mixed>  $booking
     * @return Booking
     */
    private function public(array $booking): array
    {
        return [
            'id' => (string) ($booking['id'] ?? ''),
            'eventId' => isset($booking['eventId']) ? (string) $booking['eventId'] : null,
            'status' => (string) ($booking['status'] ?? 'confirmed'),
            'startsAt' => (string) ($booking['startsAt'] ?? ''),
            'endsAt' => (string) ($booking['endsAt'] ?? ''),
            'timezone' => (string) ($booking['timezone'] ?? 'UTC'),
            'manageUrl' => isset($booking['manageUrl']) ? (string) $booking['manageUrl'] : null,
            'icalUrl' => isset($booking['icalUrl']) ? (string) $booking['icalUrl'] : null,
            'locationUrl' => isset($booking['locationUrl']) ? (string) $booking['locationUrl'] : null,
            'externalId' => isset($booking['externalId']) ? (string) $booking['externalId'] : null,
        ];
    }
}
