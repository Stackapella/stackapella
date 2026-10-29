<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminSecondFactor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminSecondFactor>
 */
final class AdminSecondFactorFactory extends Factory
{
    protected $model = AdminSecondFactor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'totp_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
            'recovery_codes' => [],
            'failed_attempts' => 0,
        ];
    }
}
