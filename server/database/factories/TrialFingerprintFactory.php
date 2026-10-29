<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrialFingerprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrialFingerprint>
 */
final class TrialFingerprintFactory extends Factory
{
    protected $model = TrialFingerprint::class;

    public function definition(): array
    {
        return ['fingerprint' => Str::random(16), 'user_id' => User::factory()];
    }
}
