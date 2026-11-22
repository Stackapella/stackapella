<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OwnChoirMember;
use App\Models\OwnChoirRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnChoirMember>
 */
final class OwnChoirMemberFactory extends Factory
{
    protected $model = OwnChoirMember::class;

    public function definition(): array
    {
        return ['own_choir_request_id' => OwnChoirRequest::factory(), 'user_id' => User::factory(), 'parts' => ['soprano'], 'consent_version' => 1, 'words_sha' => null, 'show_name' => false, 'joined_at' => now()];
    }
}
