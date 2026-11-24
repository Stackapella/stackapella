<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Growth\AiChoiceGrowth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiChoiceGrowthRequest;
use Illuminate\Http\JsonResponse;

/** admin.growth.ai: Admin > Growth's AI choice card (App\Actions\Growth\AiChoiceGrowth). */
final class AiChoiceGrowthController extends Controller
{
    public function __invoke(AiChoiceGrowthRequest $request, AiChoiceGrowth $growth): JsonResponse
    {
        return response()->json(['data' => $growth->build((string) ($request->validated('period') ?? '28d'))]);
    }
}
