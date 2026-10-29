<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Groups;

use App\Actions\Groups\Rehearsals;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventSchedule\PickScheduleRequest;
use App\Http\Requests\Groups\ListEsMembersRequest;
use App\Http\Requests\Groups\PickEventSongsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * groups.eventSchedule.* and groups.rehearsals.*: a choir group's season in Event Schedule.
 */
final class RehearsalsController extends Controller
{
    public function __construct(private readonly Rehearsals $rehearsals) {}

    public function index(string $group, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->rehearsals->list($user, $group)]);
    }

    public function link(PickScheduleRequest $request, string $group, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->rehearsals->link($user, $group, (string) $request->validated('schedule'))]);
    }

    public function unlink(string $group, #[CurrentUser] User $user): Response
    {
        $this->rehearsals->unlink($user, $group);

        return response()->noContent();
    }

    public function songs(PickEventSongsRequest $request, string $group, string $event, #[CurrentUser] User $user): JsonResponse
    {
        /** @var list<string> $templateIds */
        $templateIds = array_values((array) $request->validated('templateIds'));

        return response()->json(['data' => $this->rehearsals->pickSongs($user, $group, $event, $templateIds)]);
    }

    public function members(ListEsMembersRequest $request, string $group, #[CurrentUser] User $user): JsonResponse
    {
        $cursor = $request->validated('cursor');
        $page = $this->rehearsals->members($user, $group, is_string($cursor) ? $cursor : null);

        return response()->json(['data' => $page['members'], 'meta' => ['nextCursor' => $page['next']]]);
    }
}
