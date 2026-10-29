<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Projects;

use App\Actions\Projects\PurgeFromBin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Usage\Bin;
use App\Support\Usage\Plans;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * usage.show, bin.list, and taking things out of the bin for good (bin.purge, bin.purgeTakes).
 */
final class UsageController extends Controller
{
    public function show(Plans $plans, #[CurrentUser] User $user): JsonResponse
    {
        $usage = $plans->usage($user);
        // The storage screen's rows: each song's current and older takes (only here, not in every
        // answer that carries the usage).
        $usage['storage']['projects'] = $plans->bytesByProject($user);

        return response()->json(['data' => $usage]);
    }

    public function bin(Bin $bin, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $bin->items($user)]);
    }

    public function purge(string $kind, string $id, PurgeFromBin $purge, #[CurrentUser] User $user): Response
    {
        $kind === 'project' ? $purge->project($user, $id) : $purge->take($user, $id);

        return response()->noContent();
    }

    public function purgeTakes(string $project, PurgeFromBin $purge, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => ['freedBytes' => $purge->olderTakes($user, $project)]]);
    }
}
