<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChoirGalleryItem;
use App\Models\OwnChoirRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A choir song added to the gallery, waiting for staff. Pass the song (own_choir_request_id) and its share.
 *
 * @extends Factory<ChoirGalleryItem>
 */
final class ChoirGalleryItemFactory extends Factory
{
    protected $model = ChoirGalleryItem::class;

    public function definition(): array
    {
        return [
            'own_choir_request_id' => OwnChoirRequest::factory(),
            'user_id' => fn (array $item): int => (int) OwnChoirRequest::query()->whereKey($item['own_choir_request_id'])->value('user_id'),
            'harmony_id' => null,
            'parts_sha' => str_repeat('0', 64),
            'status' => ChoirGalleryItem::PENDING,
            'consent_version' => 1,
            'words_hash' => hash('sha256', 'the sheet'),
            'locale' => 'en',
            'added_at' => now(),
        ];
    }
}
