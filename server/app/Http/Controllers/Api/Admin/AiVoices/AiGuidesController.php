<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\AiVoices;

use App\Actions\AiVoices\AiReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiGuidesDecisionRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * admin.aiGuides.*: Hillel's review of new AI guides (docs/ai-voices-build-plan.md, phase 1c).
 */
final class AiGuidesController extends Controller
{
    public function index(Request $request, AiReview $review): JsonResponse
    {
        $state = $request->query('status', 'pending');

        return response()->json($review->list(is_string($state) && in_array($state, AiReview::STATES, true) ? $state : 'pending'));
    }

    public function approve(AiGuidesDecisionRequest $request, AiReview $review, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => ['approved' => $review->approve($this->ids($request), $admin, $request->ip())]]);
    }

    public function reject(AiGuidesDecisionRequest $request, AiReview $review, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => ['rejected' => $review->reject($this->ids($request), (string) $request->validated('note'), $admin, $request->ip())]]);
    }

    /**
     * @return list<string>
     */
    private function ids(AiGuidesDecisionRequest $request): array
    {
        return array_values(array_map('strval', (array) $request->validated('ids')));
    }
}
