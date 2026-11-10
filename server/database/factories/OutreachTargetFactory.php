<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OutreachTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachTarget>
 */
final class OutreachTargetFactory extends Factory
{
    protected $model = OutreachTarget::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Singers';

        return [
            'ref' => 'o-'.substr(md5($name), 0, 10),
            'name' => $name,
            'group' => 'barbershop',
            'country' => 'US',
            'reach' => '40 chapters',
            'fit' => 'Their chapters ask members to learn parts at home before rehearsal.',
            'contact_email' => fake()->unique()->userName().'@example.com',
            'contact_form_url' => null,
            'source_url' => 'https://example.com/contact',
            'angle' => 'choir_groups',
            'status' => 'ready',
        ];
    }
}
