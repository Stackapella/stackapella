<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Groups\AdminSeats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Groups\AdminSeatsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.groups.seats.show and .update: a choir's seats, for staff.
 */
final class GroupSeatsController extends Controller
{
    public function show(string $group, AdminSeats $seats): JsonResponse
    {
        return response()->json(['data' => $seats->show($group)]);
    }

    public function update(AdminSeatsRequest $request, string $group, AdminSeats $seats, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var array{action: string, from?: string|null, to?: string|null, reason: string} $data */
        $data = $request->validated();

        return response()->json(['data' => $seats->update($admin, $group, $data, $request->ip())]);
    }
}
