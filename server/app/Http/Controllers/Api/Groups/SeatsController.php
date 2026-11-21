<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Groups;

use App\Actions\Groups\ManageSeats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\MoveSeatRequest;
use App\Http\Requests\Groups\SeatsCheckoutRequest;
use App\Http\Requests\Groups\SeatsRequest;
use App\Models\User;
use App\Support\Billing\Withdrawal;
use App\Support\Groups\GroupView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * groups.seats.*: a choir's seats, bought and managed by its leader (docs/choir-seats-plan.md). Its services come by
 * the method, never the constructor: a controller is kept between requests, and the seats are worked out per request.
 */
final class SeatsController extends Controller
{
    public function checkout(SeatsCheckoutRequest $request, string $group, ManageSeats $seats, #[CurrentUser] User $user): JsonResponse
    {
        $url = $seats->checkout($user, $group, (int) $request->validated('seats'), (string) $request->validated('currency'), Withdrawal::country($request), $request->boolean('startNowConsent'));

        return response()->json(['data' => ['url' => $url]], 201);
    }

    public function update(SeatsRequest $request, string $group, ManageSeats $seats, GroupView $view, #[CurrentUser] User $user): JsonResponse
    {
        $updated = $seats->update($user, $group, (int) $request->validated('seats'));

        return response()->json(['data' => $view->of($updated->loadMissing('director'), $user)]);
    }

    public function move(MoveSeatRequest $request, string $group, ManageSeats $seats, GroupView $view, #[CurrentUser] User $user): JsonResponse
    {
        $moved = $seats->move($user, $group, (string) $request->validated('from'), (string) $request->validated('to'));

        return response()->json(['data' => $view->of($moved->loadMissing('director'), $user)]);
    }
}
