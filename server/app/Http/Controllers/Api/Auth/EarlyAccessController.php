<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Counts\RecordCounts;
use App\Http\Controllers\Controller;
use App\Support\Accounts\EarlyAccess;
use App\Support\Pages\MudeoArrival;
use App\Support\Trust\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * earlyAccess.show: whether the early-access link still lets people in (docs/mudeo-transition-plan.md).
 * An open one marks the visit as from mudeo (MudeoArrival), so the landing shows mudeo's welcome.
 */
final class EarlyAccessController extends Controller
{
    public function __invoke(Request $request, string $code, RecordCounts $counts): JsonResponse
    {
        $status = EarlyAccess::status($code);
        $response = response()->json(['data' => ['status' => $status]]);

        // The mudeo email's link opened: counted once a browser visit, as a number only.
        if ($status === 'open' && $request->cookies->get(MudeoArrival::COOKIE) !== '1') {
            $counts->server('mudeo_visit', $request->user() !== null, Country::of($request));
        }

        return $status === 'open' ? $response->withCookie(MudeoArrival::cookie($request)) : $response;
    }
}
