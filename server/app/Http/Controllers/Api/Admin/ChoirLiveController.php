<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Growth\ChoirLive;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** admin.choir.live: Admin > Choir's Live panel (App\Actions\Growth\ChoirLive). */
final class ChoirLiveController extends Controller
{
    public function __invoke(ChoirLive $live): JsonResponse
    {
        return response()->json(['data' => $live->build()]);
    }
}
