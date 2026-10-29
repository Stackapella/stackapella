<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one error shape (contract/schemas/error.json): { "message", "code" }, plus `errors` on a
 * 422 and `retryAfter` on a 429. `message` is the plain HTTP reason for logs; the app reads `code`
 * and never shows `message`.
 */
final class ApiError
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    public static function response(int $status, string $code, array $extra = [], array $headers = []): JsonResponse
    {
        $message = Response::$statusTexts[$status] ?? 'Error';

        return new JsonResponse(['message' => $message.'.', 'code' => $code, ...$extra], $status, [
            'Cache-Control' => 'no-store',
            ...$headers,
        ]);
    }

    /**
     * The generic code for a status, when nothing more specific applies.
     */
    public static function codeFor(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404, 405 => 'not_found',
            409 => 'out_of_date',
            419 => 'csrf_mismatch',
            422 => 'validation_failed',
            426 => 'app_outdated',
            429 => 'rate_limited',
            503 => 'unavailable',
            default => $status >= 500 ? 'server_error' : 'bad_request',
        };
    }
}
