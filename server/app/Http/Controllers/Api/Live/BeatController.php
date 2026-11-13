<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Support\Admin\StaffDevice;
use App\Support\Live\LiveBeats;
use App\Support\Stats\Crawler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * live.beat: the app's and the public pages' heartbeat for Admin > Live (docs/realtime-dashboard-plan.md). Anyone, no
 * session or cookie of its own, always a quiet 204 (a beacon never reads its answer); a crawler's and a malformed beat
 * count nothing.
 */
final class BeatController extends Controller
{
    public function __invoke(Request $request, LiveBeats $beats): Response
    {
        $area = $request->input('area');
        $page = $request->input('page');

        if (is_string($area) && is_string($page) && ! Crawler::is($request)) {
            $beats->record($area, $page, $request->boolean('view'), StaffDevice::is($request));
        }

        return response()->noContent();
    }
}
