<?php

declare(strict_types=1);

namespace App\Services\InvoiceNinja;

use RuntimeException;
use Throwable;

/**
 * Invoice Ninja refused or couldn't be reached, with a plain reason the coach is shown in words
 * (after Event Schedule's reason keys): `unreachable`, `tls`, `bad_token`, `not_allowed` (the
 * address isn't public), `redirected`, `refused` (it answered, but made nothing).
 */
final class InvoiceNinjaError extends RuntimeException
{
    public const UNREACHABLE = 'unreachable';

    public const TLS = 'tls';

    public const BAD_TOKEN = 'bad_token';

    public const NOT_ALLOWED = 'not_allowed';

    public const REDIRECTED = 'redirected';

    public const REFUSED = 'refused';

    public function __construct(public readonly string $reason, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }
}
