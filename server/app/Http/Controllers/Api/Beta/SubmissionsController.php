<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Actions\Beta\CompleteSubmission;
use App\Actions\Beta\StartSubmission;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBetaSinger;
use App\Http\Requests\Beta\StartSubmissionRequest;
use App\Http\Resources\Beta\BetaSubmissionResource;
use App\Models\BetaAssignment;
use App\Models\BetaSubmission;
use App\Support\Ids;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * beta.submissions.create and beta.submissions.complete: a take sent by a presigned PUT.
 */
final class SubmissionsController extends Controller
{
    public function store(StartSubmissionRequest $request, StartSubmission $start): JsonResponse
    {
        $assignment = BetaAssignment::query()->with(['template.currentVersion', 'clip'])->find(Ids::decode((string) $request->validated('assignmentId')) ?? 0);

        if ($assignment === null) {
            throw new Refusal(403, 'not_yours');
        }

        /** @var array{templateVersion?: int|null, key?: string|null, sampleRate: int, frames: int, latencyFrames: int, bytes: int, sha256: string, device?: string|null, mic?: string|null, scores?: array<string, mixed>|null, note?: string|null} $take */
        $take = $request->safe()->except('assignmentId');
        $result = $start->handle(EnsureBetaSinger::singer($request), $assignment, $take);
        $result['submission']->setRelation('templateVersion', $assignment->template?->currentVersion);

        return response()->json([
            'data' => BetaSubmissionResource::fields($result['submission']),
            'upload' => [...$result['upload'], 'expiresAt' => Carbon::instance($result['upload']['expiresAt'])->utc()->toIso8601ZuluString()],
        ], 201);
    }

    public function complete(Request $request, BetaSubmission $submission, CompleteSubmission $complete): BetaSubmissionResource
    {
        $submission->load(['assignment', 'templateVersion']);

        return new BetaSubmissionResource($complete->handle(EnsureBetaSinger::singer($request), $submission));
    }
}
