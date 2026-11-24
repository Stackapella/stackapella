<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\SingingTest\SingingTestReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\ReasonRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * admin.singingTests.flags.list, .review and .hide: singing tests held for the boards.
 */
final class SingingTestFlagsController extends Controller
{
    public function flags(SingingTestReview $review): JsonResponse
    {
        return response()->json(['data' => $review->flags()]);
    }

    public function review(Request $request, string $test, SingingTestReview $review, #[CurrentUser] User $admin): Response
    {
        $review->review($admin, $test, $request->ip());

        return response()->noContent();
    }

    public function hide(ReasonRequest $request, string $test, SingingTestReview $review, #[CurrentUser] User $admin): Response
    {
        $review->hide($admin, $test, (string) $request->validated('reason'), $request->ip());

        return response()->noContent();
    }
}
