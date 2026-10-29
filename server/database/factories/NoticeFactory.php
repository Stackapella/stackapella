<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notice>
 */
final class NoticeFactory extends Factory
{
    protected $model = Notice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(40)),
            'kind' => Notice::HARMONY_HIDDEN,
            'rule' => 'community_guidelines',
            'note' => 'The title broke the community guidelines.',
            'automatic' => false,
        ];
    }
}
