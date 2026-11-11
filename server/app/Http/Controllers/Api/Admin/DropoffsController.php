<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropoffReport;
use Illuminate\Http\JsonResponse;

/**
 * admin.dropoffs.show: the latest weekly drop-off report and the one before (App\Actions\Growth\Dropoffs), for Admin
 * Home's "What needs you now" (its worst three) and the Drop-offs tab.
 */
final class DropoffsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $reports = DropoffReport::query()->orderByDesc('week_end')->limit(2)->get();

        return response()->json(['data' => [
            'latest' => $reports->get(0)?->data,
            'previous' => $reports->get(1)?->data,
        ]]);
    }
}
