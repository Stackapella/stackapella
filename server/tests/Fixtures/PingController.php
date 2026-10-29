<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Http\JsonResponse;

/**
 * A route the middleware tests can hang their middleware on.
 */
final class PingController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['data' => ['ok' => true]]);
    }
}
