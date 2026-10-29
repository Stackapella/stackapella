<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\Beta\ManageGuides;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\BetaSubmission;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Http\Hosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Real voices across a new version (Hillel, 2026-10-28): a recorded voice (a template guide, or an
 * accepted beta submission not published yet) pinned to an older version moves to the current one
 * when its part sings the same there: every note's beat, length and pitch, and the song's key and
 * tempo (words may differ). Otherwise it stays on its version, and learners of the current one hear
 * what's left. Hillel is emailed once per song and version with each part's outcome. Runs in every
 * templates sync; safe to run again.
 */
final class CarryVoicesOver
{
    public const CARRIED = 'templates.voices_carried';

    public const REPORTED = 'templates.voices_reported';

    /**
     * @return list<string> one line per voice moved, for the sync's report
     */
    public function handle(): array
    {
        $moved = [];

        foreach (Template::query()->official()->with('currentVersion')->whereNotNull('current_version_id')->get() as $template) {
            $current = $template->currentVersion;

            if ($current === null) {
                continue;
            }

            /** @var array<int, array<string, mixed>> $older each earlier version's song, by its id */
            $older = TemplateVersion::query()->where('template_id', $template->id)->where('version', '<', $current->version)->pluck('data', 'id')->map(static fn (mixed $data): array => (array) $data)->all();

            if ($older === []) {
                continue;
            }

            /** @var array<string, 'carried'|'changed'> $parts */
            $parts = [];
            $carried = DB::transaction(function () use ($current, $older, &$parts): array {
                $carried = [];

                foreach (TemplateGuide::query()->whereIn('template_version_id', array_keys($older))->lockForUpdate()->get() as $guide) {
                    if (! self::sameMusic($older[$guide->template_version_id] ?? [], (array) $current->data, $guide->part_key)) {
                        $parts[$guide->part_key] ??= 'changed';

                        continue;
                    }

                    $this->moveGuide($guide, $current);
                    $parts[$guide->part_key] = 'carried';
                    $carried[] = "guide {$guide->id} {$guide->part_key}";
                }

                $waiting = BetaSubmission::query()->whereIn('template_version_id', array_keys($older))->where('status', SubmissionStatus::Accepted)->whereDoesntHave('guide')->lockForUpdate()->get();

                foreach ($waiting as $submission) {
                    if (! self::sameMusic($older[(int) $submission->template_version_id] ?? [], (array) $current->data, (string) $submission->part_key)) {
                        $parts[$submission->part_key] ??= 'changed';

                        continue;
                    }

                    $submission->forceFill(['template_version_id' => $current->id])->save();
                    $parts[$submission->part_key] = 'carried';
                    $carried[] = "submission {$submission->id} {$submission->part_key}";
                }

                return $carried;
            });

            if ($carried !== []) {
                AuditLog::record(self::CARRIED, $current, null, after: ['voices' => $carried]);

                foreach ($carried as $line) {
                    $moved[] = "{$template->slug}@{$current->version}: {$line}";
                }
            }

            $this->report($template, $current, $parts);
        }

        return $moved;
    }

    /**
     * Whether a part sings the same in two versions: its notes' beats, lengths and pitches, and the
     * song's key and tempo. The words, and every other part, may differ.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function sameMusic(array $old, array $new, string $part): bool
    {
        $notes = static function (array $song) use ($part): ?array {
            foreach ((array) ($song['parts'] ?? []) as $candidate) {
                if (is_array($candidate) && ($candidate['key'] ?? null) === $part) {
                    return array_map(static fn (mixed $note): array => is_array($note) ? [(float) ($note['beat'] ?? 0), (float) ($note['length'] ?? 0), (int) ($note['pitch'] ?? 0)] : [], (array) ($candidate['guide']['notes'] ?? []));
                }
            }

            return null;
        };

        $a = $notes($old);

        return $a !== null && $a === $notes($new) && ($old['key'] ?? null) === ($new['key'] ?? null) && (float) ($old['tempo'] ?? 0) === (float) ($new['tempo'] ?? 0);
    }

    /**
     * To the current version, its submission with it; it leads there only if nothing leads yet, and
     * the version it left gets its next leader.
     */
    private function moveGuide(TemplateGuide $guide, TemplateVersion $current): void
    {
        $from = $guide->template_version_id;
        $led = $guide->is_default;
        $taken = TemplateGuide::query()->where('template_version_id', $current->id)->where('part_key', $guide->part_key)->where('is_default', true)->exists();

        $guide->forceFill(['template_version_id' => $current->id, 'is_default' => $led && ! $taken])->save();

        if ($guide->beta_submission_id !== null) {
            BetaSubmission::query()->whereKey($guide->beta_submission_id)->update(['template_version_id' => $current->id]);
        }

        if ($led) {
            ManageGuides::fallback($from, $guide->part_key)?->forceFill(['is_default' => true])->save();
        }
    }

    /**
     * Once per song and version: each part that had an earlier version's voice, carried over or
     * needing a new recording (left out when the current version has its own voice for it).
     *
     * @param  array<string, 'carried'|'changed'>  $parts
     */
    private function report(Template $template, TemplateVersion $current, array $parts): void
    {
        $voiced = TemplateGuide::query()->where('template_version_id', $current->id)->distinct()->pluck('part_key')->all();
        $lines = [];

        foreach ((array) ($current->data['parts'] ?? []) as $part) {
            $key = is_array($part) ? ($part['key'] ?? null) : null;
            $outcome = is_string($key) ? ($parts[$key] ?? null) : null;

            if ($outcome === null || ($outcome === 'changed' && in_array($key, $voiced, true))) {
                continue;
            }

            $lines[] = __('ops.voices_carried.'.$outcome, ['part' => is_string($part['name'] ?? null) ? $part['name'] : ucfirst((string) $key)]);
        }

        if ($lines === [] || AuditLog::query()->where('action', self::REPORTED)->where('subject_type', 'template_version')->where('subject_id', $current->id)->exists()) {
            return;
        }

        $words = ['song' => $template->title, 'version' => $current->version];
        $to = app(Admins::class)->all();

        if ($to->isNotEmpty()) {
            Notification::send($to, new OpsAlert(__('ops.voices_carried.subject', $words), __('ops.voices_carried.title'), [
                __('ops.voices_carried.intro', $words),
                ...$lines,
                __('ops.voices_carried.choose', ['url' => Hosts::appUrl().'/admin/guides']),
            ]));
        }

        AuditLog::record(self::REPORTED, $current, null, after: ['parts' => $parts]);
    }
}
