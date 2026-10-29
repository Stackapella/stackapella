<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBetaSinger;
use App\Http\Resources\Beta\BetaPartResource;
use App\Models\BetaAssignment;
use App\Models\TemplateGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * beta.parts.list: My parts.
 */
final class PartsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $singer = EnsureBetaSinger::singer($request);

        $assignments = BetaAssignment::query()
            ->with(['template.currentVersion', 'latestSubmission.templateVersion', 'clip.submission'])
            ->where('beta_singer_id', $singer->id)
            ->orderByDesc('id')
            ->get()
            // Clips first, by course, lesson, step and slot (My parts' headings); then the songs' parts, newest first.
            ->sortBy(static fn (BetaAssignment $a): array => $a->clip !== null ? [0, $a->clip->course_id, (string) $a->clip->lesson_id, (int) $a->clip->step, $a->clip->slot] : [1, '', '', 0, ''])
            ->values();

        $guides = TemplateGuide::query()
            ->with(['submission.assignment.singer.user', 'creditUser'])
            ->whereIn('template_version_id', $assignments->map(static fn (BetaAssignment $a): ?int => $a->template?->current_version_id)->filter()->unique()->values())
            ->where('is_default', true)
            ->get()
            ->groupBy('template_version_id');

        return response()->json([
            'data' => $assignments->map(fn (BetaAssignment $assignment): array => (new BetaPartResource($assignment, $guides->get((int) $assignment->template?->current_version_id, collect())))->toArray($request))->values(),
        ]);
    }
}
