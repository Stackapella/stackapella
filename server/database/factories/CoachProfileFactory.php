<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CoachProfile>
 */
final class CoachProfileFactory extends Factory
{
    protected $model = CoachProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => CoachProfile::APPROVED,
            'bio' => 'Choir director and voice teacher.',
            'voice_types' => ['alto'],
            'styles' => ['choral'],
            'languages' => ['en'],
            'timezone' => 'Europe/London',
            'cancellation_hours' => 24,
            'stripe_account_id' => 'acct_'.Str::lower(Str::random(16)),
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'approved_at' => now(),
        ];
    }
}
