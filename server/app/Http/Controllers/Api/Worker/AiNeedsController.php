<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Worker;

use App\Actions\AiVoices\AiNeeds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\AiNeedsRequest;
use Illuminate\Http\JsonResponse;

/**
 * worker.ai.needs: what a song's current version still needs, for the batch renderer.
 */
final class AiNeedsController extends Controller
{
    public function __invoke(AiNeedsRequest $request, AiNeeds $needs): JsonResponse
    {
        return response()->json($needs->handle((string) $request->validated('template')), 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
