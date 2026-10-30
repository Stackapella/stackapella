<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Counts\Growth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin > Growth (docs/admin-growth-plan.md): admin.growth.show, the Overview's tiles and steps, and
 * admin.sources.show, where people came from. Daily totals only.
 */
final class GrowthController extends Controller
{
    public function overview(Request $request, Growth $growth): JsonResponse
    {
        return response()->json(['data' => $growth->overview(self::days($request, [1, 7, 30], 1))]);
    }

    public function sources(Request $request, Growth $growth): JsonResponse
    {
        return response()->json(['data' => $growth->sources(self::days($request, [1, 7, 30, 90], 7))]);
    }

    /**
     * @param  list<int>  $allowed
     */
    private static function days(Request $request, array $allowed, int $default): int
    {
        $days = (int) $request->query('days', (string) $default);

        return in_array($days, $allowed, true) ? $days : $default;
    }
}
