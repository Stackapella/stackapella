<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\Overview;
use Illuminate\Http\JsonResponse;

/**
 * admin.overview: the numbers that matter, from our own records.
 */
final class OverviewController extends Controller
{
    public function __invoke(Overview $overview): JsonResponse
    {
        return response()->json(['data' => $overview->numbers()]);
    }
}
