<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Harmony;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
final class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'reportable_type' => 'harmony',
            'reportable_id' => Harmony::factory(),
            'reporter_id' => null,
            'reporter_key' => hash('sha256', fake()->uuid()),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'reason' => 'copyright',
            'note' => '',
            'status' => Report::OPEN,
        ];
    }
}
