<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OwnChoirConsent;
use App\Models\User;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnChoirConsent>
 */
final class OwnChoirConsentFactory extends Factory
{
    protected $model = OwnChoirConsent::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'version' => OwnChoir::termsVersion(), 'accepted_at' => now()];
    }
}
