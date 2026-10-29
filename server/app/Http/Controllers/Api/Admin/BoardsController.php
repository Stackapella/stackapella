<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Boards\Boards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\ReasonRequest;
use App\Models\PartScore;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The admin's part leaderboard actions: the scores held for a look, clearing one, one score off the
 * boards, or all of a person's (audited).
 */
final class BoardsController extends Controller
{
    public function flags(Boards $boards): JsonResponse
    {
        return response()->json(['data' => $boards->flags()]);
    }

    public function review(Request $request, PartScore $score, Boards $boards, #[CurrentUser] User $admin): Response
    {
        $boards->review($admin, $score, $request->ip());

        return response()->noContent();
    }

    public function hide(ReasonRequest $request, PartScore $score, Boards $boards, #[CurrentUser] User $admin): Response
    {
        $boards->hide($admin, $score, (string) $request->validated('reason'), $request->ip());

        return response()->noContent();
    }

    public function reset(ReasonRequest $request, User $person, Boards $boards, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => ['removed' => $boards->reset($admin, $person, (string) $request->validated('reason'), $request->ip())]]);
    }
}
