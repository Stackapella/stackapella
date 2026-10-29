<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BellItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BellItem>
 */
final class BellItemFactory extends Factory
{
    protected $model = BellItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'follow',
            'actor_id' => User::factory(),
        ];
    }
}
