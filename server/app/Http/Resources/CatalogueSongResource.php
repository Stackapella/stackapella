<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Template;
use App\Models\TemplateGuide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A song in the catalogue (contract/schemas/catalogue.song.json): never its notes.
 *
 * @property Template $resource
 */
final class CatalogueSongResource extends JsonResource
{
    /**
     * @param  Collection<string, Collection<int, TemplateGuide>>  $guides  every published version by "versionId:part" (the default first), or empty when the caller can't start this song
     */
    public function __construct(Template $template, private readonly Collection $guides)
    {
        parent::__construct($template);
    }

    /**
     * @return Collection<int, TemplateGuide>
     */
    private function versions(?int $versionId, string $partKey): Collection
    {
        return $this->guides->get($versionId.':'.$partKey) ?? new Collection;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $template = $this->resource;
        $version = $template->currentVersion;
        /** @var array<string, mixed> $data */
        $data = $version->data ?? [];
        /** @var list<array{key: string, name: string, range: array{low: int, high: int}}> $parts */
        $parts = (array) ($data['parts'] ?? []);

        return [
            'id' => $template->slug,
            'version' => (int) $version?->version,
            'title' => $template->title,
            'description' => (string) ($data['description'] ?? ''),
            'difficulty' => (string) ($data['difficulty'] ?? 'beginner'),
            'genre' => array_values((array) ($data['genre'] ?? [])),
            'language' => (string) ($data['language'] ?? 'en'),
            'key' => (string) ($data['key'] ?? ''),
            'tempo' => $data['tempo'] ?? 0,
            'timeSignature' => (string) ($data['timeSignature'] ?? '4/4'),
            'reviewed' => (bool) $version?->reviewed,
            'access' => $template->access->value,
            'freeUntil' => $template->isFreeOn(now()) ? $template->free_until?->copy()->endOfDay()->utc()->toIso8601ZuluString() : null,
            'previewUrl' => $template->preview_key !== null ? Storage::disk('media')->url($template->preview_key) : null,
            // Which parts sing for each number of singers (the library card's "2 to 4 singers"); an
            // object always, {} when the song has none.
            'voicings' => (object) array_map(static fn (mixed $keys): array => array_values(array_filter((array) $keys, 'is_string')), (array) ($data['voicings'] ?? [])),
            'parts' => array_map(fn (array $part): array => [
                'key' => $part['key'],
                'name' => $part['name'],
                'range' => ['low' => $part['range']['low'], 'high' => $part['range']['high']],
                'guide' => ($guide = $this->versions($version?->id, $part['key'])->firstWhere('is_default', true)) !== null ? GuideRecording::of($guide) : null,
                // Every published version, for the learner's choice of voice (the default first).
                'versions' => $this->versions($version?->id, $part['key'])->map(static fn (TemplateGuide $g): array => [...GuideRecording::of($g), 'default' => $g->is_default])->values()->all(),
            ], $parts),
        ];
    }
}
