<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TemplateAccess;
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
        return self::finish(self::fixed($this->resource), $this->guides, $this->voiced);
    }

    /**
     * What a song says whoever asks and whenever: everything from its current version and its row, but not its voices
     * or its season. The catalogue keeps these (Catalogue::listing) so the library never reads every song's notes for
     * a request; self::finish() adds the rest. Its `_` keys are for finish() alone, never sent.
     *
     * @return array<string, mixed>
     */
    public static function fixed(Template $template): array
    {
        $version = $template->currentVersion;
        $data = $version->data ?? [];
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        // Each part's coarse shape for Home's covers (no notes, no words): a little arithmetic on the notes.
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
            // Arrays here, objects in self::finish(): the cache restores no objects (cache.serializable_classes).
            'titles' => SongWords::titles((string) $template->slug),
            'language' => (string) ($data['language'] ?? 'en'),
            'key' => (string) ($data['key'] ?? ''),
            'tempo' => $data['tempo'] ?? 0,
            'timeSignature' => (string) ($data['timeSignature'] ?? '4/4'),
            'reviewed' => (bool) $version?->reviewed,
            // When it opened in the library (its first reviewed version), for Home's "New and in season"; null while hidden.
            'openedAt' => $template->opened_at?->utc()->toIso8601ZuluString(),
            // In season now (config templates.seasons): self::finish(), by the day.
            'season' => null,
            // free: the app bundles it, guests too; account: any signed-in account (2026-11-07).
            'access' => $template->access->api(),
            // The song of the month ended (2026-11-07); always null, for older apps.
            'freeUntil' => null,
            'previewUrl' => $template->preview_key !== null ? Storage::disk('media')->url($template->preview_key) : null,
            // Which parts sing for each number of singers (the library card's "2 to 4 singers"); an
            // object always, {} when the song has none.
            'voicings' => array_map(static fn (mixed $keys): array => array_values(array_filter((array) $keys, 'is_string')), (array) ($data['voicings'] ?? [])),
            'parts' => array_map(static fn (array $part): array => [
                'key' => $part['key'],
                'name' => $part['name'],
                'range' => ['low' => $part['range']['low'], 'high' => $part['range']['high']],
                'shape' => $shapes[$part['key']] ?? [],
            ], $parts),
            '_versionId' => $version?->id,
            '_open' => Catalogue::isOpen($template),
            '_free' => $template->access === TemplateAccess::Free,
        ];
    }

    /**
     * A song as the library sends it: self::fixed() with its season today, each part's voices (only for a caller who may
     * start it: `$guides` is empty otherwise) and whether a real voice sings it.
     *
     * @param  array<string, mixed>  $fixed
     * @param  Collection<string, Collection<int, TemplateGuide>>  $guides
     * @param  array<string, bool>  $voiced
     * @return array<string, mixed>
     */
    public static function finish(array $fixed, Collection $guides, array $voiced): array
    {
        $versionId = $fixed['_versionId'];
        $versions = static fn (string $part): Collection => $guides->get($versionId.':'.$part) ?? new Collection;
        $parts = array_map(static fn (array $part): array => [
            ...$part,
            'guide' => ($guide = $versions($part['key'])->firstWhere('is_default', true)) instanceof TemplateGuide ? GuideRecording::of($guide) : null,
            // Every published version, for the learner's choice of voice (the default first).
            'versions' => $versions($part['key'])->map(static fn (TemplateGuide $g): array => [...GuideRecording::of($g), 'default' => $g->is_default])->values()->all(),
            // A real voice sings it (docs/open-guide-voices-plan.md), whether or not this caller may start the song.
            'hasRealGuide' => isset($voiced[$versionId.':'.$part['key']]),
        ], $fixed['parts']);
        $song = [...$fixed, 'titles' => (object) $fixed['titles'], 'season' => self::season($fixed['genre']), 'voicings' => (object) $fixed['voicings'], 'parts' => $parts,
            // An open song with a part still on the synth: "Be the voice" may ask for it.
            'wantsVoice' => $fixed['_open'] && collect($parts)->contains(static fn (array $part): bool => ! $part['hasRealGuide']),
        ];
        unset($song['_versionId'], $song['_open'], $song['_free']);

        return $song;
    }
}
