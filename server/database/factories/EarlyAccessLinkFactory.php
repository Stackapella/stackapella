<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EarlyAccessLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EarlyAccessLink>
 */
final class EarlyAccessLinkFactory extends Factory
{
    protected $model = EarlyAccessLink::class;

    public function definition(): array
    {
        $code = Str::random(40);

        return [
            'label' => EarlyAccessLink::MUDEO,
            'code_hash' => hash('sha256', $code),
            'code' => $code,
            'cap' => EarlyAccessLink::DEFAULT_CAP,
            'joined' => 0,
        ];
    }
}
