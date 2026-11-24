<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

use App\Actions\OwnChoir\ChoirTogether;
use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;

/**
 * Plus's choir settings (Hillel, 2026-11-23): a song's mix, saved at once and free, and how it's sung, applied by a remake
 * charged like a song (fine quality at its real GPU cost). Unset, each is today's sound.
 */
final class ChoirSettings
{
    // "natural" timing unless chosen (voices, 2026-11-23: what the worker sings when none is chosen).
    // Vibrato and natural variation (Hillel, 2026-11-23) replace humanize, kept one release for older apps, derived.
    public const SUNG = ['vibrato' => 'natural', 'variation' => 'natural', 'humanize' => 2, 'voicing' => 'auto', 'breaths' => 'legato', 'quality' => 'standard', 'ensembleTiming' => 'natural', 'pitchSpread' => 'off'];

    public const ROOMS = ['dry', 'small', 'hall', 'chapel'];

    /** Whether the singer saved a room for the song: until then the app plays it in their remembered room. */
    public static function soundSaved(OwnChoirRequest $song): bool
    {
        return in_array(((array) ($song->sound ?? []))['room'] ?? null, self::ROOMS, true);
    }

    /**
     * The song's mix, every part in its balance (0 dB unless set).
     *
     * @return array{room: string, balance: list<array{partKey: string, db: float}>, leadBlend: float|null, recordingLevel: float|null}
     */
    public static function sound(OwnChoirRequest $song): array
    {
        $saved = (array) ($song->sound ?? []);
        $levels = [];

        foreach ((array) ($saved['balance'] ?? []) as $entry) {
            if (is_array($entry) && is_string($entry['partKey'] ?? null)) {
                $levels[$entry['partKey']] = (float) ($entry['db'] ?? 0);
            }
        }

        $keys = VoicingTransform::keys((array) ($song->templateVersion->data ?? []), $song->voicing);
        $doubled = $song->voicing === 'full' ? array_merge(...array_map(static fn (string $k): array => str_ends_with($k, '-low') ? [$k, substr($k, 0, -4)] : [], $keys)) : [];

        return [
            // Small room unless they chose one (Hillel, 2026-11-24: "small room should be the default reverb").
            'room' => in_array($saved['room'] ?? null, self::ROOMS, true) ? (string) $saved['room'] : 'small',
            // Both octaves: each doubled pair 3 dB down, so tenor and bass aren't buried.
            'balance' => array_map(static fn (string $key): array => ['partKey' => $key, 'db' => $levels[$key] ?? (in_array($key, $doubled, true) ? -3.0 : 0.0)], $keys),
            'leadBlend' => isset($saved['leadBlend']) ? (float) $saved['leadBlend'] : null,
            'recordingLevel' => isset($saved['recordingLevel']) ? (float) $saved['recordingLevel'] : null,
        ];
    }

    /**
     * How it's sung.
     *
     * @return array{vibrato: string, variation: string, humanize: int, voicing: string, breaths: string, quality: string, ensembleTiming: string, pitchSpread: string}
     */
    public static function sung(OwnChoirRequest $request): array
    {
        return self::normalise((array) ($request->sung ?? []));
    }

    /**
     * Settings as given, the defaults filled in; an older app's humanize alone read as vibrato and variation, and
     * humanize derived from vibrato for it.
     *
     * @param  array<string, mixed>  $given
     * @return array{vibrato: string, variation: string, humanize: int, voicing: string, breaths: string, quality: string, ensembleTiming: string, pitchSpread: string}
     */
    public static function normalise(array $given): array
    {
        $fromHumanize = [0 => ['none', 'steady'], 1 => ['light', 'natural'], 2 => ['natural', 'natural'], 3 => ['strong', 'loose']];

        if (! isset($given['vibrato']) && isset($given['humanize']) && isset($fromHumanize[(int) $given['humanize']])) {
            [$given['vibrato'], $given['variation']] = $fromHumanize[(int) $given['humanize']];
        }

        $sung = array_replace(self::SUNG, array_intersect_key($given, self::SUNG));
        $sung['humanize'] = (int) (array_search($sung['vibrato'], ['none', 'light', 'natural', 'strong'], true) ?: 0);

        return $sung;
    }

    /**
     * The parts a remake makes again: the owner's, never one a friend sings now.
     *
     * @return list<string>
     */
    public static function remakeParts(OwnChoirRequest $song, string $voicing = 'written'): array
    {
        $friends = ChoirTogether::activeList($song)->pluck('parts')->flatten()->map(static fn (mixed $p): string => (string) $p)->all();
        $keys = VoicingTransform::keys((array) ($song->templateVersion->data ?? []), $voicing);

        // A friend's part is never remade, nor its low twin.
        return array_values(array_filter($keys, static fn (string $k): bool => ! in_array(str_ends_with($k, '-low') ? substr($k, 0, -4) : $k, $friends, true)));
    }

    /**
     * What a remake would use, as charged: its parts' minutes, fine at its real GPU cost.
     *
     * @param  list<string>|null  $parts  the parts it makes (remakeParts), when the caller has them
     */
    public static function remakeMinutes(OwnChoirRequest $song, string $quality, ?array $parts = null, string $voicing = 'written'): float
    {
        $data = (array) ($song->templateVersion->data ?? []);
        $data = $voicing === 'full' ? VoicingTransform::full($data)['data'] : $data;
        $parts ??= self::remakeParts($song, $voicing);
        $data['parts'] = array_values(array_filter((array) ($data['parts'] ?? []), static fn (mixed $p): bool => is_array($p) && in_array($p['key'] ?? null, $parts, true)));
        $minutes = OwnChoir::partMinutes($data);

        return round($quality === 'fine' ? $minutes * (float) config('own_choir.fine_factor', 2.0) : $minutes, 1);
    }

    /** The song's latest remake not yet folded in (queued, rendering, or failed until the next). */
    public static function remakeOf(OwnChoirRequest $song): ?OwnChoirRequest
    {
        // Only the latest: one made (folded in, emptied) clears an earlier failure.
        $latest = $song->relationLoaded('friendJobs') ? $song->friendJobs->where('remake', true)->sortByDesc('id')->first()
            : OwnChoirRequest::query()->where('parent_request_id', $song->id)->where('remake', true)->latest('id')->first();

        if ($latest === null || $latest->status === OwnChoirStatus::Deleted) {
            return null;
        }

        // A remake sings its song's version: the one already read.
        if ($latest->template_version_id === $song->template_version_id && $song->relationLoaded('templateVersion')) {
            $latest->setRelation('templateVersion', $song->templateVersion);
        }

        return $latest;
    }
}
