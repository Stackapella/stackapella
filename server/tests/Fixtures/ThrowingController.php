<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use RuntimeException;

final class ThrowingController
{
    public function __invoke(): never
    {
        throw new RuntimeException('The database password is hunter2');
    }
}
