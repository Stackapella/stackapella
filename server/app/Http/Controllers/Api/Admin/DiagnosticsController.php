<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Trust\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * admin.diagnostics.country: what Hillel's own request carried from Cloudflare, and whether the app
 * trusted it, to check country tracking in one click. Nothing is stored.
 */
final class DiagnosticsController extends Controller
{
    public function country(Request $request): JsonResponse
    {
        return response()->json(['data' => Country::diagnose($request)]);
    }
}
