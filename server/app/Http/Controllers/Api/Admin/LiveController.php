<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Live\LiveNow;
use App\Support\Live\LivePulse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin > Numbers > Live (docs/realtime-dashboard-plan.md): admin.live.show, polled every 15 seconds, and
 * admin.pulse.show, every minute. Numbers only, never who (the day's new accounts as Admin > People shows them).
 */
final class LiveController extends Controller
{
    public function show(Request $request, LiveNow $live): JsonResponse
    {
        return response()->json(['data' => $live->show($request->boolean('admins'))]);
    }

    public function pulse(LivePulse $pulse): JsonResponse
    {
        return response()->json(['data' => $pulse->show()]);
    }
}
