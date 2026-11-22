<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Growth\ChoirInsights;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChoirInsightsRequest;
use Illuminate\Http\JsonResponse;

/** admin.choir.show: the choir's anonymous numbers over 7 or 30 days (App\Actions\Growth\ChoirInsights). */
final class ChoirInsightsController extends Controller
{
    public function __invoke(ChoirInsightsRequest $request, ChoirInsights $insights): JsonResponse
    {
        return response()->json(['data' => $insights->build((string) ($request->validated('period') ?? '30d'))]);
    }
}
