<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Growth\Breadth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * admin.breadth.show: how much of the app singers use, from br:<n> and ft:<feature> alone (App\Actions\Growth\Breadth),
 * for the Breadth tab under Growth and its line on Admin Home.
 */
final class BreadthController extends Controller
{
    public function __invoke(Breadth $breadth): JsonResponse
    {
        return response()->json(['data' => $breadth->build()]);
    }
}
