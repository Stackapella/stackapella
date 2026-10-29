<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Actions\Beta\ReviewSubmission;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Beta\AdjustSubmissionRequest;
use App\Http\Requests\Admin\Beta\DecideSubmissionRequest;
use App\Http\Requests\Admin\Beta\ListSubmissionsRequest;
use App\Http\Resources\Admin\BetaSubmissionResource;
use App\Http\Resources\GuideRecording;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Http\CursorList;
use App\Support\Ids;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * admin.beta.submissions.*: the review queue and Hillel's decisions.
 */
final class SubmissionsController extends Controller
{
    public function index(ListSubmissionsRequest $request): JsonResponse
    {
        $status = $request->validated('status');
        $template = $request->validated('template');
        $part = $request->validated('part');
        $singer = $request->validated('singer');
        $singerId = is_string($singer) ? Ids::decode($singer) : null;

        $page = BetaSubmission::query()
            ->with(BetaSubmissionResource::RELATIONS)
            ->when($status === 'waiting', fn ($query) => $query->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::InReview->value]))
            ->when(is_string($status) && $status !== 'waiting', fn ($query) => $query->where('status', $status))
            ->when(is_string($template), fn ($query) => $query->whereHas('assignment.template', fn ($q) => $q->where('slug', $template)))
            ->when(is_string($part), fn ($query) => $query->where('part_key', $part))
            // One singer's takes, from the Singers screen ("who did well"); an unknown id finds none.
            ->when(is_string($singer), fn ($query) => $query->whereHas('assignment', fn ($q) => $q->where('beta_singer_id', $singerId ?? 0)))
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return CursorList::response($page, BetaSubmissionResource::class, $request);
    }

    public function show(BetaSubmission $submission): JsonResponse
    {
        $submission->load(BetaSubmissionResource::RELATIONS);
        $expiresAt = now()->addMinutes(15);
        // No master once it's been pruned (key emptied), or before it ever arrived.
        $master = $submission->master_key === '' || (in_array($submission->status, [SubmissionStatus::Uploading, SubmissionStatus::Failed], true) && ! Storage::disk('private')->exists($submission->master_key))
            ? null
            : ['url' => Storage::disk('private')->temporaryUrl($submission->master_key, $expiresAt), 'expiresAt' => $expiresAt->utc()->toIso8601ZuluString()];

        $others = TemplateGuide::query()
            ->with(['submission.assignment.singer.user', 'creditUser'])
            ->where('template_version_id', $submission->template_version_id)
            ->where('part_key', '!=', $submission->part_key)
            ->where('is_default', true)
            ->get();

        return response()->json([
            'data' => BetaSubmissionResource::fields($submission),
            'master' => $master,
            'peaks' => $submission->peaks,
            'otherParts' => $others->map(static fn (TemplateGuide $guide): array => GuideRecording::of($guide))->values(),
        ]);
    }

    public function open(BetaSubmission $submission, ReviewSubmission $review): JsonResponse
    {
        return $this->respond($review->open($submission));
    }

    public function adjust(AdjustSubmissionRequest $request, BetaSubmission $submission, ReviewSubmission $review, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var array{offsetMs?: int, trimStartMs?: int, trimEndMs?: int, gainDb?: float|int} $adjust */
        $adjust = $request->validated();

        return $this->respond($review->adjust($submission, $adjust, $admin, $request->ip()));
    }

    public function accept(DecideSubmissionRequest $request, BetaSubmission $submission, ReviewSubmission $review, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->respond($review->accept($submission, $request->boolean('makeDefault', true), $admin, $request->ip()));
    }

    public function retake(DecideSubmissionRequest $request, BetaSubmission $submission, ReviewSubmission $review, #[CurrentUser] User $admin): JsonResponse
    {
        $beat = $request->validated('beat');

        return $this->respond($review->retake($submission, (string) $request->validated('note'), $beat === null ? null : (float) $beat, $admin, $request->ip()));
    }

    public function reject(DecideSubmissionRequest $request, BetaSubmission $submission, ReviewSubmission $review, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->respond($review->reject($submission, $request->validated('note'), $admin, $request->ip()));
    }

    public function publish(Request $request, BetaSubmission $submission, ReviewSubmission $review, #[CurrentUser] User $admin): JsonResponse
    {
        return $this->respond($review->publish($submission, $admin, $request->ip()));
    }

    private function respond(BetaSubmission $submission): JsonResponse
    {
        return response()->json(['data' => BetaSubmissionResource::fields($submission->refresh()->load(BetaSubmissionResource::RELATIONS))]);
    }
}
