<?php

declare(strict_types=1);

namespace App\Http\Resources\Beta;

use App\Actions\Courses\CourseClips;
use App\Http\Resources\GuideRecording;
use App\Models\BetaAssignment;
use App\Models\TemplateGuide;
use App\Support\Beta\PartStatus;
use App\Support\Templates\TemplateRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * One part on My parts (contract/schemas/beta.part.json). Needs template.currentVersion and
 * latestSubmission.templateVersion loaded, and the version's guides passed in.
 *
 * @property BetaAssignment $resource
 */
final class BetaPartResource extends JsonResource
{
    /**
     * @param  Collection<int, TemplateGuide>  $guides  the default real voices of this template version
     */
    public function __construct(BetaAssignment $assignment, private readonly Collection $guides)
    {
        parent::__construct($assignment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignment = $this->resource;

        // A course clip (CourseClips): its brief instead of a song, and no guides.
        if ($assignment->clip !== null) {
            $clip = $assignment->clip;
            $latest = $assignment->latestSubmission;

            return [
                'id' => $assignment->sqid(),
                'kind' => 'clip',
                'template' => null,
                'partKey' => null,
                'style' => 'free',
                'status' => PartStatus::of($latest, $clip->published_at !== null && $clip->submission?->beta_assignment_id === $assignment->id),
                'recordable' => $clip->retired_at === null,
                'latestSubmission' => $latest !== null ? BetaSubmissionResource::fields($latest) : null,
                'guides' => [],
                'clip' => [...CourseClips::brief($clip), 'courseTitle' => CourseClips::courseTitle($clip->course_id)],
            ];
        }

        $template = $assignment->template ?? throw new \LogicException('An assignment is a part or a clip.');
        $version = $template->currentVersion;
        /** @var array<string, mixed> $data */
        $data = $version->data ?? [];
        $latest = $assignment->latestSubmission;

        return [
            'id' => $assignment->sqid(),
            'kind' => 'part',
            'template' => [
                'id' => $template->slug,
                'version' => (int) $version?->version,
                'title' => $template->title,
                'key' => (string) ($data['key'] ?? ''),
                'tempo' => $data['tempo'] ?? 0,
                'reviewed' => (bool) $version?->reviewed,
            ],
            'partKey' => $assignment->part_key,
            // How to use vibrato on this part (docs/vibrato-plan.md): the part's own, else the song's.
            'style' => self::style($data, (string) $assignment->part_key),
            'status' => PartStatus::of($latest, $this->guides->contains(fn (TemplateGuide $guide): bool => $guide->submission?->beta_assignment_id === $assignment->id)),
            'recordable' => (bool) $version?->reviewed,
            'latestSubmission' => $latest !== null ? BetaSubmissionResource::fields($latest) : null,
            // `mine`: this singer's own take, told apart from another singer's on the same part.
            'guides' => $this->guides->map(static fn (TemplateGuide $guide): array => [...GuideRecording::of($guide), 'mine' => $guide->submission?->assignment->beta_singer_id === $assignment->beta_singer_id])->values()->all(),
            'clip' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function style(array $data, string $partKey): string
    {
        $part = collect((array) ($data['parts'] ?? []))->first(fn ($p): bool => is_array($p) && ($p['key'] ?? null) === $partKey);
        $style = is_array($part) && is_string($part['style'] ?? null) ? $part['style'] : ($data['style'] ?? 'free');

        return in_array($style, TemplateRules::STYLES, true) ? $style : 'free';
    }
}
