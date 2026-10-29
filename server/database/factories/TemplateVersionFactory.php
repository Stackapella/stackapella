<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateVersion>
 */
final class TemplateVersionFactory extends Factory
{
    protected $model = TemplateVersion::class;

    public function definition(): array
    {
        $data = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true);

        return [
            'template_id' => Template::factory(),
            'version' => 1,
            'reviewed' => false,
            'data' => $data,
            'sha256' => hash('sha256', (string) json_encode($data)),
            'created_at' => now(),
        ];
    }
}
