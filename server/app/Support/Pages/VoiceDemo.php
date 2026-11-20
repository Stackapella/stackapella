<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Enums\GuideSource;
use App\Http\Resources\GuideRecording;
use App\Models\AiVoice;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\AiVoices\AiGuides;
use App\Support\Languages\SongWords;
use App\Support\Templates\Catalogue;

/**
 * Hear yourself as a choir's demo (/your-choir; Hillel, 2026-11-20: "the name audio should come form the app", "have her
 * singing two part harmony os silent ight"): a singer's real, published guide beside AI guides her own AI voice sings
 * on a song she never sang, both exactly as the app serves them (config/site.php `voice_demo`). Shown only while AI
 * voices are on for everyone, the voice is consented, active and credited (its singer chose to be named), its AI guides
 * for every part asked are published on the song's current version, and the real guide is live with its credit;
 * otherwise nothing, and the page shows no demo.
 */
final class VoiceDemo
{
    public function __construct(private readonly Catalogue $catalogue) {}

    /**
     * @return list<array{kind: string, tempo: float, voices: list<array<string, mixed>>}>
     */
    public function cards(): array
    {
        $demo = (array) config('site.voice_demo');
        $voice = AiVoice::query()->find((int) ($demo['ai_voice'] ?? 0));
        $name = $voice?->labelName();
        if (! AiGuides::on(null) || $voice === null || $name === null) {
            return [];
        }

        $real = $this->real((array) ($demo['real'] ?? []));
        $ai = $this->ai($voice, (array) ($demo['ai'] ?? []));
        if ($real === null || $ai === null) {
            return [];
        }

        return [
            ['kind' => 'real', 'tempo' => $real['tempo'], 'voices' => $real['voices']],
            ['kind' => 'ai', 'tempo' => $ai['tempo'], 'voices' => $ai['voices']],
        ];
    }

    /**
     * The real side: the part's default recorded guide on its song's current version (or the guide `guide` names), with
     * its singer's credit.
     *
     * @param  array<array-key, mixed>  $side
     * @return array{credit: string, title: string, tempo: float, voices: list<array<string, mixed>>}|null
     */
    private function real(array $side): ?array
    {
        $template = $this->song((string) ($side['song'] ?? ''));
        $part = (string) ($side['part'] ?? '');
        if ($template === null || $part === '') {
            return null;
        }
        $guides = $this->catalogue->guides([(int) $template->current_version_id])->get($template->current_version_id.':'.$part);
        $guide = isset($side['guide']) && (int) $side['guide'] > 0
            ? $guides?->first(static fn (TemplateGuide $g): bool => $g->id === (int) $side['guide'])
            : $guides?->first();
        $recording = $guide !== null && ! $guide->isAi() ? GuideRecording::of($guide) : null;
        if ($recording === null || $recording['credit'] === null) {
            return null;
        }

        return ['credit' => $recording['credit'], 'title' => self::title($template), 'tempo' => self::tempo($template), 'voices' => [[...self::voice($template, $part, $recording), 'part' => $part]]];
    }

    /**
     * The AI side: the voice's published AI guides for the parts asked, on the song's current version: every `required`
     * one, and the others that are there.
     *
     * @param  array<array-key, mixed>  $side
     * @return array{title: string, tempo: float, voices: list<array<string, mixed>>}|null
     */
    private function ai(AiVoice $voice, array $side): ?array
    {
        $template = $this->song((string) ($side['song'] ?? ''));
        $parts = array_values(array_filter((array) ($side['parts'] ?? []), 'is_string'));
        if ($template === null || $parts === []) {
            return null;
        }
        // As the app and the site may hear them: a consented, active voice's, and (with phase 1c) approved ones only.
        $guides = AiGuides::shownWith(true)
            ->where('source', GuideSource::Ai->value)
            ->where('ai_voice_id', $voice->id)
            ->where('template_version_id', $template->current_version_id)
            ->whereIn('part_key', $parts)
            ->with('aiVoice')
            ->get()
            ->keyBy('part_key');
        // Every part in `required` must be there; the others join when published (the stage's four-part scene).
        $required = array_values(array_filter((array) ($side['required'] ?? $parts), 'is_string'));
        $voices = [];
        foreach ($parts as $part) {
            $guide = $guides->get($part);
            if (! $guide instanceof TemplateGuide) {
                if (in_array($part, $required, true)) {
                    return null;
                }

                continue;
            }
            $voices[] = [...self::voice($template, $part, GuideRecording::of($guide)), 'part' => $part];
        }

        return ['title' => self::title($template), 'tempo' => self::tempo($template), 'voices' => $voices];
    }

    private function song(string $slug): ?Template
    {
        return $slug === '' ? null : Template::query()->where('slug', $slug)->whereNull('hidden_at')->with('currentVersion')->first();
    }

    private static function title(Template $template): string
    {
        return SongWords::title((string) $template->title, (string) $template->slug);
    }

    private static function tempo(Template $template): float
    {
        return (float) (((array) $template->currentVersion?->data)['tempo'] ?? 80);
    }

    /**
     * One voice as the player reads it: the recording as the app's API gives it, with its part's notes, so its breaths
     * are softened as every library voice's are (js/soften-breaths.js).
     *
     * @param  array<string, mixed>  $recording
     * @return array<string, mixed>
     */
    private static function voice(Template $template, string $part, array $recording): array
    {
        $notes = [];
        foreach ((array) (((array) $template->currentVersion?->data)['parts'] ?? []) as $p) {
            if (is_array($p) && ($p['key'] ?? null) === $part) {
                $notes = array_values((array) ($p['guide']['notes'] ?? []));
            }
        }

        return [...$recording, 'notes' => $notes];
    }
}
