<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Growth\Sharing;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * admin.sharing.show: how often singers pass the app on this week and what comes of it (App\Actions\Growth\Sharing),
 * for Admin Home's Sharing card and the Sharing view under Numbers.
 */
final class SharingController extends Controller
{
    public function __invoke(Sharing $sharing): JsonResponse
    {
        return response()->json(['data' => $sharing->build()]);
    }
}
