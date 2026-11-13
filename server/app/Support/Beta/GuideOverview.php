<?php

declare(strict_types=1);

namespace App\Support\Beta;

use App\Enums\SubmissionStatus;
use App\Http\Resources\Admin\BetaGuideResource;
use App\Models\BetaAssignment;
use App\Models\BetaSubmission;
use App\Models\Report;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Support\Templates\Staging;
use Illuminate\Support\Collection;

/**
 * Every template's real voices, per part (default first, then alternates), and how many are left
 * from older versions: "3 of 4 parts are real voices" (admin.beta.guides.list). Each part also
 * says what needs Hillel now: takes waiting for review on the current version, a real voice that
 * didn't carry to it (the part changed: record again), and no singer who can still record it, with
 * the singers asked for it (admin.beta.needs.show is the same, only what needs him).
 */
final class GuideOverview
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(?string $slug): array
    {
        $templates = Template::query()->official()
            ->with(['currentVersion', 'stagedVersion'])
            ->when($slug !== null && $slug !== '', fn ($query) => $query->where('slug', $slug))
            ->whereNotNull('current_version_id')
            ->orderBy('title')
            ->get();

        $guides = TemplateGuide::query()
            ->with(['submission.assignment.singer.user', 'creditUser', 'templateVersion'])
            ->whereHas('templateVersion', fn ($query) => $query->whereIn('template_id', $templates->pluck('id')))
            ->orderByDesc('is_default')
            ->orderByDesc('published_at')
            ->get();
        // Open reports on each voice (Lend your voice's moderation), read once.
        $reports = Report::query()->where('reportable_type', (new TemplateGuide)->getMorphClass())->whereIn('reportable_id', $guides->modelKeys())->where('status', Report::OPEN)
            ->selectRaw('reportable_id, count(*) as n')->groupBy('reportable_id')->pluck('n', 'reportable_id');
        $guides->each(fn (TemplateGuide $g) => $g->setAttribute('open_reports', (int) ($reports[$g->id] ?? 0)));

        $assignments = BetaAssignment::query()->with(['singer', 'latestSubmission'])
            ->whereIn('template_id', $templates->pluck('id'))->whereNotNull('part_key')->orderBy('id')->get();
        // Takes waiting for review, and accepted ones not published: each with its version and part.
        $takes = BetaSubmission::query()->with('assignment.singer')
            ->whereIn('beta_assignment_id', $assignments->modelKeys())
            ->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::InReview, SubmissionStatus::Accepted])
            ->orderBy('id')->get();

        return $templates->map(function (Template $template) use ($guides, $assignments, $takes): array {
            $version = $template->currentVersion;
            $current = $guides->where('template_version_id', $version?->id);
            /** @var list<array{key: string, name: string}> $parts */
            $parts = (array) ($version->data['parts'] ?? []);

            return [
                'id' => $template->slug,
                'version' => (int) $version?->version,
                'title' => $template->title,
                'reviewed' => (bool) $version?->reviewed,
                'parts' => array_map(fn (array $part): array => [
                    'key' => $part['key'],
                    'name' => $part['name'],
                    'guides' => $current->where('part_key', $part['key'])->map(static fn (TemplateGuide $g): array => BetaGuideResource::fields($g))->values()->all(),
                    'needs' => $this->needs(
                        $template,
                        $part['key'],
                        $guides->filter(static fn (TemplateGuide $g): bool => $g->templateVersion->template_id === $template->id && $g->part_key === $part['key']),
                        $assignments->where('template_id', $template->id)->where('part_key', $part['key']),
                        $takes->filter(static fn (BetaSubmission $t): bool => $t->assignment->template_id === $template->id && $t->part_key === $part['key']),
                    ),
                ], $parts),
                // A staged next version (the content's <id>.next.json): its number, and the parts whose notes change in
                // it, which need new voices (the others' voices move over at the switch, CarryVoicesOver).
                'staged' => $template->stagedVersion === null ? null : ['version' => $template->stagedVersion->version, 'changed' => Staging::changed($template)],
                'olderGuides' => $guides->filter(static fn (TemplateGuide $g): bool => $g->templateVersion->template_id === $template->id && $g->template_version_id !== $version?->id)->count(),
            ];
        })->values()->all();
    }

    /**
     * Only what needs Hillel now, song by song (admin.beta.needs.show): the parts with takes to
     * review, a voice to record again, or no singer; and how many takes wait in all.
     *
     * @return array{review: int, templates: list<array<string, mixed>>}
     */
    public function summary(): array
    {
        $templates = [];
        $review = 0;

        foreach ($this->templates(null) as $template) {
            $parts = [];

            foreach ((array) $template['parts'] as $part) {
                $needs = $part['needs'];
                $review += count($needs['review']);

                if ($needs['review'] !== [] || $needs['changed'] || $needs['unassigned']) {
                    $parts[] = ['key' => $part['key'], 'name' => $part['name'], 'needs' => $needs];
                }
            }

            if ($parts !== [] && $template['reviewed']) {
                $templates[] = ['id' => $template['id'], 'version' => $template['version'], 'title' => $template['title'], 'parts' => $parts];
            }
        }

        return ['review' => $review, 'templates' => $templates];
    }

    /**
     * One part's state on the current version.
     *
     * @param  Collection<int, TemplateGuide>  $guides  every published voice of this part, any version
     * @param  Collection<int, BetaAssignment>  $assignments  the singers asked for this part
     * @param  Collection<int, BetaSubmission>  $takes  its takes waiting for review or accepted
     * @return array{voiced: bool, review: list<array<string, mixed>>, changed: bool, unassigned: bool, singers: list<array<string, mixed>>}
     */
    private function needs(Template $template, string $partKey, Collection $guides, Collection $assignments, Collection $takes): array
    {
        $currentId = $template->current_version_id;
        $voiced = $guides->contains(static fn (TemplateGuide $g): bool => $g->template_version_id === $currentId && $g->is_default);
        // A real voice (published, or accepted and waiting) left on an older version: the part changed there.
        $older = $guides->where('template_version_id', '!=', $currentId);
        $olderTakes = $takes->filter(static fn (BetaSubmission $t): bool => $t->status === SubmissionStatus::Accepted && $t->template_version_id !== $currentId);
        $previous = $older->map(static fn (TemplateGuide $g): ?int => $g->submission?->assignment->beta_singer_id)
            ->merge($olderTakes->map(static fn (BetaSubmission $t): int => $t->assignment->beta_singer_id))->filter()->unique()->values()->all();

        $singers = $assignments->map(static function (BetaAssignment $a) use ($currentId, $previous): array {
            $latest = $a->latestSubmission;
            $status = PartStatus::of($latest, false, $a->askedAgain());
            $onCurrent = $latest === null || $latest->template_version_id === $currentId;
            // Could still give the current version a voice: asked again, still to record, or a take
            // sent for it; never one whose take was refused, or who's gone.
            $open = $a->singer->revoked_at === null && $a->singer->forgotten_at === null
                && ($a->askedAgain() || in_array($status, ['to_record', 'retake'], true) || ($onCurrent && in_array($status, ['submitted', 'in_review', 'accepted'], true)));

            return [
                'assignment' => $a->sqid(),
                'singer' => ['id' => $a->singer->sqid(), 'name' => $a->singer->name],
                'status' => $status,
                'open' => $open,
                'previous' => in_array($a->beta_singer_id, $previous, true),
                'askedAgainAt' => $a->askedAgain() ? $a->asked_again_at?->utc()->toIso8601ZuluString() : null,
            ];
        })->values();

        return [
            'voiced' => $voiced,
            // Takes for the current version, or for a staged next one (its new voices, reviewed before the switch).
            'review' => array_values($takes->filter(static fn (BetaSubmission $t): bool => in_array($t->template_version_id, [$currentId, $template->staged_version_id], true) && $t->status->isWaiting())
                ->map(static fn (BetaSubmission $t): array => ['submission' => $t->sqid(), 'status' => $t->status->value, 'version' => $t->template_version_id === $template->staged_version_id ? (int) $template->stagedVersion?->version : (int) $template->currentVersion?->version, 'singer' => ['id' => $t->assignment->singer->sqid(), 'name' => $t->assignment->singer->name]])->all()),
            'changed' => ! $voiced && ($older->isNotEmpty() || $olderTakes->isNotEmpty()),
            'unassigned' => ! $voiced && ! $singers->contains('open', true),
            'singers' => array_values($singers->all()),
        ];
    }
}
