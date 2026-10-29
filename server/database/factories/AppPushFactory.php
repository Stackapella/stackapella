<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppPush;
use App\Models\BellItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppPush>
 */
final class AppPushFactory extends Factory
{
    protected $model = AppPush::class;

    public function definition(): array
    {
        $item = BellItem::factory()->create();

        return [
            'user_id' => $item->user_id,
            'bell_item_id' => $item->id,
            'send_after' => now(),
        ];
    }
}
