<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SyncTombstone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SyncTombstone>
 */
final class SyncTombstoneFactory extends Factory
{
    protected $model = SyncTombstone::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'kind' => 'part', 'uuid' => (string) Str::uuid(), 'seq' => 1];
    }
}
