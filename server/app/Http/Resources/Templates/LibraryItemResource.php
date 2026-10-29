<?php

declare(strict_types=1);

namespace App\Http\Resources\Templates;

use App\Actions\Templates\Library;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One template in the library (N3): what its card shows, so the list needs no other call (the
 * parts' ranges for "fits my voice" included), and its `library`.
 *
 * @property Template $resource
 */
final class LibraryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::of($this->resource, $request->user() instanceof User ? $request->user() : null);
    }

    /**
     * @param  array<int, int>|null  $ratings  the viewer's stars by template (Library::ratingsOf)
     * @return array<string, mixed>
     */
    public static function of(Template $template, ?User $viewer, ?array $ratings = null): array
    {
        /** @var array<string, mixed> $data */
        $data = $template->currentVersion->data ?? [];
        $tempo = is_numeric($data['tempo'] ?? null) ? (int) $data['tempo'] : 0;
        $beats = 0.0;

        foreach ((array) ($data['sections'] ?? []) as $section) {
            $beats = max($beats, is_array($section) && is_numeric($section['endBeat'] ?? null) ? (float) $section['endBeat'] : 0.0);
        }

        return [
            'id' => $template->slug,
            'version' => (int) $template->currentVersion?->version,
            'title' => $template->title,
            'description' => (string) ($data['description'] ?? ''),
            'difficulty' => (string) ($data['difficulty'] ?? 'beginner'),
            'genre' => array_values(array_map('strval', (array) ($data['genre'] ?? []))),
            'key' => (string) ($data['key'] ?? ''),
            'tempo' => $tempo,
            'timeSignature' => (string) ($data['timeSignature'] ?? '4/4'),
            'durationSeconds' => $tempo > 0 ? (int) round($beats * 60 / $tempo) : 0,
            'parts' => array_values(array_map(static fn (array $part): array => [
                'key' => (string) $part['key'],
                'name' => (string) ($part['name'] ?? $part['key']),
                'range' => ['low' => (int) ($part['range']['low'] ?? 0), 'high' => (int) ($part['range']['high'] ?? 0)],
            ], array_filter((array) ($data['parts'] ?? []), static fn (mixed $part): bool => is_array($part) && is_string($part['key'] ?? null)))),
            'voicings' => array_values(array_map('intval', $template->voicings ?? [])),
            'library' => app(Library::class)->view($template, $viewer, $ratings),
        ];
    }
}
