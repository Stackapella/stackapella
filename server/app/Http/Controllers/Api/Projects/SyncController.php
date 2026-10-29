<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\SyncPullRequest;
use App\Models\User;
use App\Support\Sync\Changes;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * sync.pull.
 */
final class SyncController extends Controller
{
    public function __invoke(SyncPullRequest $request, Changes $changes, #[CurrentUser] User $user): JsonResponse
    {
        $cursor = $request->validated('cursor');

        return response()->json($changes->since($user, is_string($cursor) ? $cursor : null));
    }
}
