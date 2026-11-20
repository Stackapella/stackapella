<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\AiVoices;

use App\Actions\AiVoices\WithdrawAiVoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AiVoiceResource;
use App\Models\AiVoice;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * admin.aiVoices.*: the AI guide voices (docs/ai-voices-build-plan.md), and withdrawing one.
 */
final class AiVoicesController extends Controller
{
    public function index(): JsonResponse
    {
        $voices = AiVoice::query()->with(['guides.templateVersion.template', 'betaSinger'])->orderByDesc('id')->get();

        return response()->json(['data' => AiVoiceResource::collection($voices)->resolve()]);
    }

    public function withdraw(Request $request, AiVoice $voice, WithdrawAiVoice $withdraw, #[CurrentUser] User $admin): JsonResponse
    {
        $retired = $withdraw->handle($voice, $admin, $request->ip());

        return response()->json(['data' => (new AiVoiceResource($voice->load(['guides.templateVersion.template', 'betaSinger'])))->resolve(), 'meta' => ['retired' => $retired]]);
    }
}
