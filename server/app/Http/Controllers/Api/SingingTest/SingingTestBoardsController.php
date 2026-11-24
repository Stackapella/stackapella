<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SingingTest;

use App\Actions\SingingTest\SingingTestBoards;
use App\Http\Controllers\Controller;
use App\Http\Requests\SingingTest\SingingTestBoardsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * singingTests.boards.show: the singing test's Most improved and Best boards, behind singing_test_boards.
 */
final class SingingTestBoardsController extends Controller
{
    public function __invoke(SingingTestBoardsRequest $request, SingingTestBoards $boards, #[CurrentUser] User $user): JsonResponse
    {
        $month = $request->validated('month');

        return response()->json(['data' => $boards->show($user, (string) $request->validated('board'), (string) ($request->validated('scope') ?? 'global'), is_string($month) ? $month : null)]);
    }
}
