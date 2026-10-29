<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DataExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataExport>
 */
final class DataExportFactory extends Factory
{
    protected $model = DataExport::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'status' => 'preparing'];
    }
}
