<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SingingTest;

use App\Actions\SingingTest\ListSingingTests;
use App\Actions\SingingTest\MarkWarmUp;
use App\Actions\SingingTest\SaveSingingTest;
use App\Http\Controllers\Controller;
use App\Http\Requests\SingingTest\SaveSingingTestRequest;
use App\Http\Requests\SingingTest\UpdateSingingTestRequest;
use App\Http\Resources\SingingTest\SingingTestResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * singingTests.list, .create and .update: the singing test's results (docs/singing-test-v1.md), behind singing_test.
 */
final class SingingTestsController extends Controller
{
    public function index(ListSingingTests $list, #[CurrentUser] User $user): JsonResponse
    {
        return SingingTestResource::collection($list->handle($user))->response();
    }

    public function store(SaveSingingTestRequest $request, SaveSingingTest $save, #[CurrentUser] User $user): JsonResponse
    {
        $saved = $save->handle($user, $request->validated());

        return (new SingingTestResource($saved['test']))->response()->setStatusCode($saved['created'] ? 201 : 200);
    }

    public function update(UpdateSingingTestRequest $request, string $test, MarkWarmUp $mark, #[CurrentUser] User $user): JsonResponse
    {
        return (new SingingTestResource($mark->handle($user, $test, $request->boolean('warmUp'))))->response();
    }
}
