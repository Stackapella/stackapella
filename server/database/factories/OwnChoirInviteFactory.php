<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OwnChoirInvite;
use App\Models\OwnChoirRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OwnChoirInvite>
 */
final class OwnChoirInviteFactory extends Factory
{
    protected $model = OwnChoirInvite::class;

    public function definition(): array
    {
        return ['own_choir_request_id' => OwnChoirRequest::factory(), 'token_hash' => hash('sha256', Str::random(32)), 'parts' => ['soprano'], 'expires_at' => now()->addDays(14)];
    }
}
