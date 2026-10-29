<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A refusal with a stable code (`not_admin`, `project_limit`, `invite_expired`...), which the app
 * words in the person's language (contract/api.md, "Errors"). Rendered as
 * { "message", "code" } with the status.
 */
final class Refusal extends HttpException
{
    /**
     * @param  array<string, mixed>  $extra  more fields for the error body (contract-defined)
     * @param  array<string, string>  $headers
     */
    public function __construct(int $status, public readonly string $refusalCode, public readonly array $extra = [], array $headers = [])
    {
        parent::__construct($status, $refusalCode, null, $headers);
    }
}
