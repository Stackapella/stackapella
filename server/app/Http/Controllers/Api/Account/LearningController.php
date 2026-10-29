<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Account;

use App\Actions\Users\SaveLearning;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\LearningRequest;
use App\Http\Requests\Account\LearningResetRequest;
use App\Models\User;
use App\Models\UserLearning;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * account.learning.show, .update, .reset and .undoReset.
 */
final class LearningController extends Controller
{
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => SaveLearning::fields(UserLearning::query()->where('user_id', $user->id)->first())]);
    }

    public function update(LearningRequest $request, SaveLearning $save, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array<string, mixed> $progress */
        $progress = $request->validated('progress');

        return response()->json(['data' => SaveLearning::fields($save->handle($user, (int) $request->validated('version'), (string) $request->validated('changeId'), $progress, (int) ($request->validated('epoch') ?? 0)))]);
    }

    public function reset(LearningResetRequest $request, SaveLearning $save, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => SaveLearning::fields($save->reset($user, (string) $request->validated('changeId')))]);
    }

    public function undoReset(LearningResetRequest $request, SaveLearning $save, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => SaveLearning::fields($save->undoReset($user, (string) $request->validated('changeId')))]);
    }
}
