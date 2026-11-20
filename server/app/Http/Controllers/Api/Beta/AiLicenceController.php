<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Actions\AiVoices\AcceptAiLicence;
use App\Actions\AiVoices\UpdateAiLicenceCredit;
use App\Actions\AiVoices\WithdrawAiLicence;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBetaSinger;
use App\Http\Requests\Beta\AcceptAiLicenceRequest;
use App\Http\Requests\Beta\UpdateAiLicenceRequest;
use App\Support\AiVoices\AiLicence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * beta.aiLicence.*: the AI voice licence, accepted, changed and withdrawn in the app (docs/ai-voices-build-plan.md,
 * phase 1b). Each answers the singer's licence state (AiLicence::state).
 */
final class AiLicenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => AiLicence::state(EnsureBetaSinger::singer($request))]);
    }

    public function accept(AcceptAiLicenceRequest $request, AcceptAiLicence $accept): JsonResponse
    {
        $singer = EnsureBetaSinger::singer($request);
        $accept->handle($singer, (int) $request->validated('version'), (bool) $request->validated('credited'), $request->ip());

        return response()->json(['data' => AiLicence::state($singer)]);
    }

    public function update(UpdateAiLicenceRequest $request, UpdateAiLicenceCredit $update): JsonResponse
    {
        $singer = EnsureBetaSinger::singer($request);
        $update->handle($singer, (bool) $request->validated('credited'), $request->ip());

        return response()->json(['data' => AiLicence::state($singer)]);
    }

    public function withdraw(Request $request, WithdrawAiLicence $withdraw): JsonResponse
    {
        $singer = EnsureBetaSinger::singer($request);
        $retired = $withdraw->handle($singer, $request->ip());

        return response()->json(['data' => AiLicence::state($singer), 'meta' => ['retired' => $retired]]);
    }
}
