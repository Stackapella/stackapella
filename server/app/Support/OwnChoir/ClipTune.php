<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

use App\Actions\OwnChoir\OwnChoirRequests;
use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Validation\ValidationException;

/**
 * The tune a choir clip sings (Hillel, 2026-11-23: "let's go with Happy Birthday"): voice-capture by default (Happy
 * Birthday, then For He's a Jolly Good Fellow; hidden, listed nowhere), whatever song is being made, or another tune the
 * singer chose from the allow-list (Hillel, 2026-11-23).
 */
final class ClipTune
{
    /** The template version a clip sings: one the singer chose from config `own_choir.clip_templates`, voice-capture by default; else 422. */
    public static function version(?string $clipTemplateId, TemplateVersion $song): TemplateVersion
    {
        $songSlug = (string) $song->template?->slug;
        $slug = $clipTemplateId ?? self::default();

        if (! array_key_exists($slug, (array) config('own_choir.clip_templates'))) {
            throw ValidationException::withMessages(['clipTemplateId' => __('validation.in', ['attribute' => 'clipTemplateId'])]);
        }

        // Hidden (withdrawn) from every listing, still served.
        if ($slug === $songSlug) {
            return $song;
        }

        return Template::query()->with('currentVersion')->where('slug', $slug)->whereNull('owner_id')->whereNull('deleted_at')->first()->currentVersion
            ?? throw new \LogicException("The clip template {$slug} is in the catalogue.");
    }

    /**
     * The tunes a singer may choose, in the order shown, each with the beat it stops on (null: the whole tune).
     *
     * @return list<array{templateId: string, stopBeat: float|null}>
     */
    public static function songs(): array
    {
        $songs = [];

        foreach ((array) config('own_choir.clip_templates') as $slug => $stop) {
            $songs[] = ['templateId' => (string) $slug, 'stopBeat' => $stop === null ? null : (float) $stop];
        }

        return $songs;
    }

    /**
     * The clip's template.json as sung: every part's guide notes starting before its stop beat, unchanged (the app trims
     * its copy by the same rule), transposed by the semitones the singer's first note moved it.
     *
     * @return array<string, mixed>
     */
    public static function sung(TemplateVersion $version, int $transpose = 0): array
    {
        $data = (array) $version->data;
        $stop = ((array) config('own_choir.clip_templates'))[(string) $version->template?->slug] ?? null;

        if ($stop === null && $transpose === 0) {
            return $data;
        }

        $data['parts'] = array_map(static function (mixed $part) use ($stop, $transpose): mixed {
            if (is_array($part) && is_array($part['guide']['notes'] ?? null)) {
                $kept = array_filter($part['guide']['notes'], static fn (mixed $n): bool => is_array($n) && ($stop === null || (float) ($n['beat'] ?? 0) < (float) $stop));
                // Transposed to the singer's first note: the notes they actually heard and sang.
                $part['guide']['notes'] = array_values(array_map(static fn (array $n): array => is_numeric($n['pitch'] ?? null) ? array_replace($n, ['pitch' => (int) $n['pitch'] + $transpose]) : $n, $kept));
            }

            return $part;
        }, (array) ($data['parts'] ?? []));

        return $data;
    }

    public static function default(): string
    {
        return (string) config('own_choir.clip_template', 'happy-birthday');
    }

    /** The tune's melody key. */
    public static function melody(TemplateVersion $version): ?string
    {
        return OwnChoirRequests::melody((array) $version->data);
    }
}
