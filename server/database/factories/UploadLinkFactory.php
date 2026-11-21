<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UploadLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UploadLink>
 */
final class UploadLinkFactory extends Factory
{
    protected $model = UploadLink::class;

    public function definition(): array
    {
        return ['upload_key' => 'uploads/'.Str::lower(Str::random(32)).'.wav', 'bytes' => 1000, 'expires_at' => now()->addMinutes(3)];
    }
}
