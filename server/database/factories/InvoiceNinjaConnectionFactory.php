<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceNinjaConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvoiceNinjaConnection>
 */
final class InvoiceNinjaConnectionFactory extends Factory
{
    protected $model = InvoiceNinjaConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'owner_key' => fn (array $attributes): string => 'user:'.$attributes['user_id'],
            'api_url' => null,
            'token' => 'in-'.Str::random(20),
            'company_id' => 'Co'.Str::random(6),
            'company_name' => 'Sarah\'s Studio',
            'send_email' => false,
            'status' => InvoiceNinjaConnection::ACTIVE,
        ];
    }

    public function platform(): self
    {
        return $this->state(['owner_key' => InvoiceNinjaConnection::PLATFORM, 'user_id' => null, 'company_name' => 'Stackapella']);
    }
}
