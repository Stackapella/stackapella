<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Beta\UpdateBarRequest;
use App\Models\User;
use App\Support\Beta\TakeBar;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.beta.bar.show and .update: the bar a beta singer's take must clear to be sent.
 */
final class BarController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => TakeBar::current()]);
    }

    public function update(UpdateBarRequest $request, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var array{pitchPercent?: int, timingPercent?: int} $changes */
        $changes = $request->validated();

        return response()->json(['data' => TakeBar::set($admin, $changes, $request->ip())]);
    }
}
