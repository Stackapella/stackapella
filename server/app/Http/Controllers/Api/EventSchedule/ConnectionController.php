<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\EventSchedule;

use App\Actions\EventSchedule\Connections;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventSchedule\ConnectWithKeyRequest;
use App\Http\Requests\EventSchedule\FinishConnectionRequest;
use App\Http\Requests\EventSchedule\PickScheduleRequest;
use App\Models\User;
use App\Services\EventSchedule\EventSchedule;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * eventSchedule.*: connecting the person's account to Event Schedule, picking a schedule, and what
 * it has (schedules, appointment types). Tokens never leave the server.
 */
final class ConnectionController extends Controller
{
    public function __construct(private readonly Connections $connections, private readonly EventSchedule $es) {}

    public function show(#[CurrentUser] User $user): JsonResponse
    {
        $connection = $this->connections->current($user);

        return response()->json(['data' => $connection !== null ? Connections::view($connection) : null]);
    }

    public function start(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->connections->start($user)]);
    }

    public function finish(FinishConnectionRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => Connections::view($this->connections->finish($user, (string) $request->validated('code'), (string) $request->validated('state')))]);
    }

    public function key(ConnectWithKeyRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => Connections::view($this->connections->withKey($user, (string) $request->validated('baseUrl'), (string) $request->validated('apiKey')))]);
    }

    public function update(PickScheduleRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => Connections::view($this->connections->pickSchedule($user, (string) $request->validated('schedule')))]);
    }

    public function destroy(#[CurrentUser] User $user): Response
    {
        $this->connections->disconnect($user);

        return response()->noContent();
    }

    public function schedules(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->es->schedules($this->connections->usable($user))]);
    }

    public function types(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->connections->types($user)]);
    }
}
