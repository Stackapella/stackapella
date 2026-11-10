<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Counts\ActiveSingers;
use App\Actions\Counts\Growth;
use App\Actions\Counts\GrowthCompare;
use App\Actions\Counts\Reach;
use App\Http\Controllers\Controller;
use App\Support\Admin\Insights;
use App\Support\Admin\Revenue;
use App\Support\Admin\Search;
use App\Support\Stats\FairCompare;
use App\Support\Stats\ReportDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin > Growth (docs/admin-growth-plan.md): admin.growth.show, the Overview's tiles and steps, and
 * admin.sources.show, where people came from. Daily totals only. And docs/admin-analytics-plan.md:
 * admin.active.show, active singers week by week, admin.reach.show, people using the app, and
 * admin.revenue.show, and admin.search.show (docs/search-console.md).
 */
final class GrowthController extends Controller
{
    public function overview(Request $request, GrowthCompare $compare): JsonResponse
    {
        // ?period= (docs/growth-compare-plan.md, 5.5), or the old ?days= an older web still sends.
        $period = $request->query('period');
        $period = is_string($period) && in_array($period, FairCompare::PERIODS, true) ? $period : (FairCompare::DAYS[(int) $request->query('days', '1')] ?? 'today');

        return response()->json(['data' => [...$compare->show($period), 'timezone' => ReportDay::zone()]]);
    }

    public function sources(Request $request, Growth $growth): JsonResponse
    {
        return response()->json(['data' => [...$growth->sources(self::days($request, [1, 7, 30, 90], 7)), 'timezone' => ReportDay::zone()]]);
    }

    public function active(ActiveSingers $active): JsonResponse
    {
        return response()->json(['data' => [...$active->show(), 'timezone' => ReportDay::zone()]]);
    }

    public function reach(Reach $reach): JsonResponse
    {
        return response()->json(['data' => [...$reach->show(), 'timezone' => ReportDay::zone()]]);
    }

    public function cohorts(Insights $insights): JsonResponse
    {
        return response()->json(['data' => ['months' => $insights->cohorts(), 'timezone' => ReportDay::zone()]]);
    }

    public function churn(Revenue $revenue): JsonResponse
    {
        return response()->json(['data' => ['months' => $revenue->churn(), 'timezone' => ReportDay::zone()]]);
    }

    public function activity(Insights $insights): JsonResponse
    {
        return response()->json(['data' => [...$insights->activity(), 'timezone' => ReportDay::zone()]]);
    }

    public function search(Search $search): JsonResponse
    {
        return response()->json(['data' => $search->show()]);
    }

    public function revenue(Revenue $revenue): JsonResponse
    {
        return response()->json(['data' => [...$revenue->show(), 'timezone' => ReportDay::zone()]]);
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
