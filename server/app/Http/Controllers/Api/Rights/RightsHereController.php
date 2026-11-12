<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Rights;

use App\Http\Controllers\Controller;
use App\Support\Rights\RightsHere;
use App\Support\Trust\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * rights.here: the songs withheld where the visitor is (docs/geo-rights-plan.md), for the site's song lists, which are
 * cached for everyone and hide those cards in the browser. The api group's no-store keeps it to this visitor.
 */
final class RightsHereController extends Controller
{
    public function __invoke(Request $request, RightsHere $rights): JsonResponse
    {
        return response()->json(['data' => [
            'country' => Country::of($request),
            'withheld' => $rights->withheldSlugs(RightsHere::country($request)),
        ]]);
    }
}
