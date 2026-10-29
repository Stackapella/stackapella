<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Beta;

use App\Actions\Beta\AssignPart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Beta\StoreAssignmentRequest;
use App\Http\Resources\Admin\BetaAssignmentResource;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\CourseClip;
use App\Models\Template;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * admin.beta.assignments.create and .delete: the parts a singer is asked to record.
 */
final class AssignmentsController extends Controller
{
    public function store(StoreAssignmentRequest $request, BetaSinger $singer, AssignPart $assign, #[CurrentUser] User $admin): JsonResponse
    {
        $minutes = $request->validated('minutes');
        $clip = $request->validated('clipId');

        if (is_string($clip)) {
            $assignment = $assign->addClip($singer, CourseClip::query()->where('slot', $clip)->firstOrFail(), $minutes === null ? null : (int) $minutes, $admin, $request->ip());

            return response()->json(['data' => BetaAssignmentResource::fields($assignment)], 201);
        }

        $assignment = $assign->add(
            $singer,
            Template::query()->official()->where('slug', (string) $request->validated('templateId'))->firstOrFail(),
            (string) $request->validated('partKey'),
            $minutes === null ? null : (int) $minutes,
            $admin,
            $request->ip(),
        );

        return response()->json(['data' => BetaAssignmentResource::fields($assignment)], 201);
    }

    public function destroy(Request $request, BetaAssignment $assignment, AssignPart $assign, #[CurrentUser] User $admin): Response
    {
        $assign->remove($assignment, $admin, $request->ip());

        return response()->noContent();
    }
}
