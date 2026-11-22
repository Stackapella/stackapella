<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

use App\Actions\Counts\RecordCounts;
use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;

/**
 * The choir's anonymous daily counts (Hillel, 2026-11-23: "make sure this is all tracked so we can analyze over time to
 * improve"), within the no-tracking rule: a number per kind and day, nothing about who (config counts.server_kinds).
 */
final class ChoirCounts
{
    public const REASONS = ['length', 'quiet', 'loud', 'noisy', 'edge', 'short', 'notes'];

    public const SONGS = ['voice-capture' => 'capture', 'silent-night' => 'silent', 'amazing-grace' => 'grace', 'joy-to-the-world' => 'joy'];

    /**
     * Every kind counted here, for config counts.server_kinds.
     *
     * @return list<string>
     */
    public static function kinds(): array
    {
        $kinds = ['choir_remade', 'choir_download', 'choir_share', 'choir_guide_on', 'choir_guide_off', 'choir_transpose_none', 'choir_transpose_small', 'choir_transpose_large',
            'choir_octave_same', 'choir_octave_down', 'choir_octave_up', 'choir_take_level_quiet', 'choir_take_level_ok', 'choir_take_level_loud', 'choir_render_seconds',
            'choir_tries_1', 'choir_tries_2', 'choir_tries_3', 'choir_tries_4', 'choir_part_tries_1', 'choir_part_tries_2', 'choir_part_tries_3', 'choir_clip_other'];

        foreach (self::SONGS as $song) {
            $kinds[] = "choir_clip_ok_{$song}";
            $kinds[] = "choir_clip_fail_{$song}";
        }

        foreach (self::REASONS as $reason) {
            $kinds[] = "choir_clip_{$reason}";
        }

        foreach (['soprano', 'alto', 'tenor', 'bass', 'other'] as $part) {
            foreach (['written', 'low', 'full'] as $voicing) {
                $kinds[] = "choir_gate_{$part}_{$voicing}";
            }
        }

        foreach (['humanize', 'voicing', 'breaths', 'quality', 'ensembleTiming', 'pitchSpread'] as $setting) {
            $kinds[] = 'choir_remake_'.strtolower($setting);
        }

        return $kinds;
    }

    /**
     * A job finished: its clip's outcome (by the song it sang, with or without headphones, moved how far, in which
     * octave, how loud), what its parts took, and a first song's tries.
     *
     * @param  array{detail?: string|null, level?: string|null, partTries?: list<array{partKey: string, tries: int}>|null, gateFailures?: list<array{partKey: string, reason?: string|null}>|null, gpuSeconds?: float|null}  $report
     */
    public static function finished(OwnChoirRequest $job, array $report): void
    {
        $count = static fn (string $kind, int $n = 1) => $n > 0 ? app(RecordCounts::class)->serverAmount($kind, $n) : null;

        if (! $job->from_voice && $job->clip_template_version_id !== null) {
            $song = self::SONGS[(string) $job->clipTemplateVersion?->template?->slug] ?? null;
            $unusable = $job->status === OwnChoirStatus::Failed && $job->failure === 'clip_unusable';

            if ($song !== null && ($unusable || $job->status === OwnChoirStatus::Ready || $job->remake)) {
                $count($unusable ? "choir_clip_fail_{$song}" : "choir_clip_ok_{$song}");
            }

            if ($unusable) {
                $count('choir_clip_'.(in_array($job->failure_detail, self::REASONS, true) ? $job->failure_detail : 'other'));
            }

            $count($job->guide ? 'choir_guide_on' : 'choir_guide_off');
            $count(match (true) {
                $job->clip_transpose === 0 => 'choir_transpose_none',
                abs($job->clip_transpose) <= 3 => 'choir_transpose_small',
                default => 'choir_transpose_large',
            });
            $octave = $job->voice?->octave;

            if ($octave !== null) {
                $count($octave === 0 ? 'choir_octave_same' : ($octave < 0 ? 'choir_octave_down' : 'choir_octave_up'));
            }
        }

        if (in_array($report['level'] ?? null, ['quiet', 'ok', 'loud'], true)) {
            $count('choir_take_level_'.$report['level']);
        }

        foreach ((array) ($report['partTries'] ?? []) as $part) {
            $count('choir_part_tries_'.min(3, max(1, (int) ($part['tries'] ?? 1))));
        }

        foreach ((array) ($report['gateFailures'] ?? []) as $failure) {
            $key = (string) ($failure['partKey'] ?? '');
            $base = str_ends_with($key, '-low') ? substr($key, 0, -4) : $key;
            $part = in_array($base, ['soprano', 'alto', 'tenor', 'bass'], true) ? $base : 'other';
            $voicing = in_array($job->voicing, ['written', 'low', 'full'], true) ? $job->voicing : 'written';
            $count("choir_gate_{$part}_{$voicing}");
        }

        if ($job->status === OwnChoirStatus::Ready || $job->remake) {
            $count('choir_render_seconds', (int) round((float) $job->gpu_seconds));
        }

        // A first song made: how many tries it took (their own earlier songs that reached the renderer and failed),
        // counted from this row's own history, never kept about them.
        if ($job->status === OwnChoirStatus::Ready && $job->parent_request_id === null) {
            $before = OwnChoirRequest::query()->where('user_id', $job->user_id)->whereNull('parent_request_id')->whereKeyNot($job->id)->where('id', '<', $job->id);

            if (! (clone $before)->whereNotNull('ready_at')->exists()) {
                $count('choir_tries_'.min(4, 1 + (clone $before)->where('status', OwnChoirStatus::Failed->value)->where('tries', '>', 0)->count()));
            }
        }
    }

    /**
     * A remake asked for: which settings changed from how the song was sung.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function remade(array $before, array $after): void
    {
        app(RecordCounts::class)->serverAmount('choir_remade', 1);

        foreach ($after as $setting => $value) {
            if (($before[$setting] ?? null) !== $value) {
                app(RecordCounts::class)->serverAmount('choir_remake_'.strtolower((string) $setting), 1);
            }
        }
    }
}
