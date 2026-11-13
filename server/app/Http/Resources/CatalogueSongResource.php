<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\Languages\SongWords;
use App\Support\Pages\SongCollections;
use App\Support\Stats\ReportDay;
use App\Support\Templates\Catalogue;
use App\Support\Templates\SongShape;
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
     * @param  array<string, bool>  $voiced  "versionId:part" for every part with a real voice, whoever is asking
     */
    public function __construct(Template $template, private readonly Collection $guides, private readonly array $voiced = [])
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
     * The season a song with these genres is in today, or null.
     *
     * @param  array<int|string, mixed>  $genres
     * @return array{key: string, until: string}|null
     */
    public static function season(array $genres): ?array
    {
        $genres = array_map(static fn (mixed $g): string => strtolower(trim((string) $g)), array_filter($genres, 'is_string'));
        $today = ReportDay::now();

        foreach ((array) config('templates.seasons') as $key => $season) {
            $year = $today->year;
            $from = $year.'-'.$season['from'];
            $until = $year.'-'.$season['until'];

            if (array_intersect($genres, (array) $season['genres']) !== [] && $today->toDateString() >= $from && $today->toDateString() <= $until) {
                return ['key' => (string) $key, 'until' => $until];
            }
        }

        return null;
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
        // Each part's coarse shape for Home's covers (no notes, no words): a little arithmetic on
        // the notes, cheaper than a cache lookup a song.
        $shapes = SongShape::of($data);

        return [
            'id' => $template->slug,
            'version' => (int) $version?->version,
            'title' => $template->title,
            'description' => (string) ($data['description'] ?? ''),
            'difficulty' => (string) ($data['difficulty'] ?? 'beginner'),
            'genre' => array_values((array) ($data['genre'] ?? [])),
            // The /songs group it sits in (SongCollections::group, as /songs and its A to Z place it), null for "more";
            // and its own titles in other languages, for the app's Browse all (frontend, 2026-11-12).
            'group' => ($group = SongCollections::group(array_values(array_filter((array) ($data['genre'] ?? []), 'is_string')))) === 'more' ? null : $group,
            'titles' => (object) SongWords::titles((string) $template->slug),
            'language' => (string) ($data['language'] ?? 'en'),
            'key' => (string) ($data['key'] ?? ''),
            'tempo' => $data['tempo'] ?? 0,
            'timeSignature' => (string) ($data['timeSignature'] ?? '4/4'),
            'reviewed' => (bool) $version?->reviewed,
            // When it opened in the library (its first reviewed version), for Home's "New and in season"; null while hidden.
            'openedAt' => $template->opened_at?->utc()->toIso8601ZuluString(),
            // In season now (config templates.seasons: carols until 26 December), for Home's tag; null otherwise.
            'season' => self::season((array) ($data['genre'] ?? [])),
            // free: the app bundles it, guests too; account: any signed-in account (2026-11-07).
            'access' => $template->access->api(),
            // The song of the month ended (2026-11-07); always null, for older apps.
            'freeUntil' => null,
            'previewUrl' => $template->preview_key !== null ? Storage::disk('media')->url($template->preview_key) : null,
            // Which parts sing for each number of singers (the library card's "2 to 4 singers"); an
            // object always, {} when the song has none.
            'voicings' => (object) array_map(static fn (mixed $keys): array => array_values(array_filter((array) $keys, 'is_string')), (array) ($data['voicings'] ?? [])),
            'parts' => array_map(fn (array $part): array => [
                'key' => $part['key'],
                'name' => $part['name'],
                'range' => ['low' => $part['range']['low'], 'high' => $part['range']['high']],
                'shape' => $shapes[$part['key']] ?? [],
                'guide' => ($guide = $this->versions($version?->id, $part['key'])->firstWhere('is_default', true)) !== null ? GuideRecording::of($guide) : null,
                // Every published version, for the learner's choice of voice (the default first).
                'versions' => $this->versions($version?->id, $part['key'])->map(static fn (TemplateGuide $g): array => [...GuideRecording::of($g), 'default' => $g->is_default])->values()->all(),
                // A real voice sings it (docs/open-guide-voices-plan.md), whether or not this caller may start the song.
                'hasRealGuide' => isset($this->voiced[$version?->id.':'.$part['key']]),
            ], $parts),
            // An open song with a part still on the synth: "Be the voice" may ask for it.
            'wantsVoice' => Catalogue::isOpen($template) && collect($parts)->contains(fn (array $part): bool => ! isset($this->voiced[$version?->id.':'.$part['key']])),
        ];
    }
}
