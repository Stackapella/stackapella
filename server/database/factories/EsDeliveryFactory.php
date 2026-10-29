<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EsConnection;
use App\Models\EsDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EsDelivery>
 */
final class EsDeliveryFactory extends Factory
{
    protected $model = EsDelivery::class;

    public function definition(): array
    {
        return [
            'es_connection_id' => EsConnection::factory(),
            'delivery_id' => 'dl-'.Str::random(10),
            'type' => 'appointment.booked',
        ];
    }
}
