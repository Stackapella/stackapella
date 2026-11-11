<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DropoffReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DropoffReport>
 */
final class DropoffReportFactory extends Factory
{
    protected $model = DropoffReport::class;

    public function definition(): array
    {
        return [
            'week_end' => '2026-11-12',
            'data' => ['weekStart' => '2026-11-06', 'weekEnd' => '2026-11-12', 'floor' => 30, 'paths' => [], 'worst' => [], 'falls' => []],
        ];
    }
}
