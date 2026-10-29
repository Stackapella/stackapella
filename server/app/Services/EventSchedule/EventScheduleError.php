<?php

declare(strict_types=1);

namespace App\Services\EventSchedule;

use RuntimeException;
use Throwable;

/**
 * Event Schedule refused or couldn't be reached. The API answers it with its own code
 * (contract/api.md, M9): `424 event_schedule_unavailable` (try again later), `409
 * reconnect_required` (ES no longer accepts the connection), `409 slot_taken` (the time went),
 * `410 connect_expired` (a sign-in code ES refused), `404 not_found` (ES has no such thing).
 */
final class EventScheduleError extends RuntimeException
{
    public const UNAVAILABLE = 'event_schedule_unavailable';

    public const RECONNECT = 'reconnect_required';

    public const SLOT_TAKEN = 'slot_taken';

    public const CONNECT_EXPIRED = 'connect_expired';

    public const NOT_FOUND = 'not_found';

    public function __construct(public readonly string $reason, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }

    public function status(): int
    {
        return match ($this->reason) {
            self::RECONNECT, self::SLOT_TAKEN => 409,
            self::CONNECT_EXPIRED => 410,
            self::NOT_FOUND => 404,
            default => 424,
        };
    }

    public static function unavailable(string $message, ?Throwable $previous = null): self
    {
        return new self(self::UNAVAILABLE, $message, $previous);
    }
}
