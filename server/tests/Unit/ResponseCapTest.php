<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Http\ResponseCap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResponseCapTest extends TestCase
{
    public function test_reading_stops_past_the_cap(): void
    {
        $progress = ResponseCap::options(1000)['progress'];
        $progress(0, 999, 0, 0);
        $progress(1000, 1000, 0, 0);

        $this->expectException(RuntimeException::class);
        $progress(0, 1001, 0, 0);
    }

    public function test_an_answer_declared_too_big_stops_at_once(): void
    {
        $this->expectException(RuntimeException::class);
        ResponseCap::options(1000)['progress'](5000, 0, 0, 0);
    }
}
