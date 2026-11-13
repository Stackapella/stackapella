<?php

declare(strict_types=1);

namespace App\Support\Beta;

use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use Illuminate\Support\Collection;

/**
 * admin.beta.submissions.list's groups (?groupBy=part; docs/open-guide-voices-plan.md, "Choosing among takes"): a page's
 * takes by song part, each with the part's guide now, for Hillel's compare view. The groups the oldest waiting take is
 * in come first. Lesson clips aren't a song's part, and aren't grouped.
 */
final class SubmissionGroups
{
    /**
     * @param  Collection<int, BetaSubmission>  $takes  a page of them, with BetaSubmissionResource::RELATIONS
     * @return list<array{templateId: string, title: string, version: int, partKey: string, partName: string, guide: array{submission: string|null, singer: array{name: string}}|null, takes: list<string>}>
     */
    public static function of(Collection $takes): array
    {
        $songs = $takes->filter(static fn (BetaSubmission $t): bool => $t->template_version_id !== null && $t->part_key !== null && $t->assignment->template !== null);
        $guides = TemplateGuide::query()->with('submission.assignment.singer')->where('is_default', true)
            ->whereIn('template_version_id', $songs->pluck('template_version_id')->unique()->values())->get()
            ->keyBy(static fn (TemplateGuide $g): string => $g->template_version_id.':'.$g->part_key);
        $waiting = [SubmissionStatus::Submitted, SubmissionStatus::InReview];

        $groups = $songs->groupBy(static fn (BetaSubmission $t): string => $t->template_version_id.':'.$t->part_key)->map(function (Collection $group, string $key) use ($guides, $waiting): array {
            /** @var BetaSubmission $first */
            $first = $group->first();
            $parts = (array) ($first->templateVersion->data['parts'] ?? []);
            $part = collect($parts)->first(static fn (mixed $p): bool => is_array($p) && ($p['key'] ?? null) === $first->part_key);
            $guide = $guides->get($key);
            $oldest = $group->filter(static fn (BetaSubmission $t): bool => in_array($t->status, $waiting, true))->min('created_at') ?? $group->min('created_at');

            return [
                'oldest' => (string) $oldest,
                'group' => [
                    'templateId' => (string) $first->assignment->template?->slug,
                    'title' => (string) $first->assignment->template?->title,
                    'version' => (int) $first->templateVersion?->version,
                    'partKey' => (string) $first->part_key,
                    'partName' => is_array($part) ? (string) ($part['name'] ?? $first->part_key) : (string) $first->part_key,
                    'guide' => $guide === null ? null : ['submission' => $guide->submission?->sqid(), 'singer' => ['name' => (string) ($guide->submission?->assignment->singer->name ?? '')]],
                    'takes' => array_values($group->map(static fn (BetaSubmission $t): string => $t->sqid())->all()),
                ],
            ];
        })->sortBy('oldest');

        return array_values($groups->map(static fn (array $g): array => $g['group'])->all());
    }
}
