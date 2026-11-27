<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Actions\Support\SupportInbox;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBetaSinger;
use App\Http\Requests\Beta\FeedbackRequest;
use Illuminate\Http\JsonResponse;

/**
 * beta.feedback.create: Tell us. Kept in the support inbox (category beta), so it can be answered.
 */
final class FeedbackController extends Controller
{
    public function __invoke(FeedbackRequest $request, SupportInbox $inbox): JsonResponse
    {
        $beat = $request->validated('beat');

        $feedback = $inbox->receiveBeta(
            EnsureBetaSinger::singer($request),
            (string) $request->validated('text'),
            $request->validated('templateId'),
            $request->validated('partKey'),
            $beat === null ? null : (float) $beat,
            (string) $request->ip(),
        );

        return response()->json(['data' => ['id' => $feedback->sqid(), 'createdAt' => $feedback->created_at?->utc()->toIso8601ZuluString()]], 201);
    }
}
