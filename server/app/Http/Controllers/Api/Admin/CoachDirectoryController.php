<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Coaching\CoachSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Gallery\FeatureRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.coaches.feature (N4): Hillel's featured coaches.
 */
final class CoachDirectoryController extends Controller
{
    public function feature(FeatureRequest $request, string $handle, CoachSearch $search, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => CoachSearch::item($search->feature($handle, $admin, $request->boolean('featured'), $request->ip()))]);
    }
}
