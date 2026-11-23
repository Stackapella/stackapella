<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Growth\ChoirGrowth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChoirInsightsRequest;
use Illuminate\Http\JsonResponse;

/** admin.growth.choirs: AI choirs on Admin > Growth over 7 or 30 days (App\Actions\Growth\ChoirGrowth). */
final class ChoirGrowthController extends Controller
{
    public function __invoke(ChoirInsightsRequest $request, ChoirGrowth $growth): JsonResponse
    {
        return response()->json(['data' => $growth->build((string) ($request->validated('period') ?? '30d'))]);
    }
}
