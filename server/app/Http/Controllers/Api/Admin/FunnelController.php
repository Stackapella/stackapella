<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Counts\Funnel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * admin.funnel.show: Admin > Funnel, an experiment's arms step by step, or every device's funnel
 * over the last few days (Funnel::report). Totals only, never anyone's path.
 */
final class FunnelController extends Controller
{
    public function __invoke(Request $request, Funnel $funnel): JsonResponse
    {
        $days = in_array((int) $request->query('days', '30'), [7, 30, 90], true) ? (int) $request->query('days', '30') : 30;
        $experiment = $request->query('experiment');

        return response()->json(['data' => $funnel->report(is_string($experiment) ? $experiment : null, $days)]);
    }
}
