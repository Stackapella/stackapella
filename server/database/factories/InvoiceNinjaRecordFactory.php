<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvoiceNinjaRecord>
 */
final class InvoiceNinjaRecordFactory extends Factory
{
    protected $model = InvoiceNinjaRecord::class;

    public function definition(): array
    {
        return [
            'connection_id' => InvoiceNinjaConnection::factory(),
            'kind' => InvoiceNinjaRecord::SALE,
            'ref' => 'sale:'.Str::random(10),
            'status' => InvoiceNinjaRecord::PENDING,
            'cents' => 2500,
            'currency' => 'gbp',
            'data' => ['description' => 'A take review', 'name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'date' => now()->toDateString(), 'charge' => 'ch_1'],
        ];
    }
}
