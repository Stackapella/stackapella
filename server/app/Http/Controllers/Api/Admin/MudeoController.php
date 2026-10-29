<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Mudeo\MudeoList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Mudeo\ImportMudeoRequest;
use App\Http\Requests\Admin\Mudeo\MarkMudeoRequest;
use App\Http\Requests\Admin\Mudeo\SearchMudeoRequest;
use App\Http\Requests\Admin\Mudeo\SendMudeoBatchRequest;
use App\Http\Requests\Admin\Mudeo\UpdateMudeoRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * admin.mudeo.*: the list for Hillel's one email to mudeo's users (docs/mudeo-transition-plan.md).
 */
final class MudeoController extends Controller
{
    public function __construct(private readonly MudeoList $list) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->list->show()]);
    }

    public function update(UpdateMudeoRequest $request, #[CurrentUser] User $admin): JsonResponse
    {
        $this->list->setCap((int) $request->validated('dailyCap'), $admin, $request->ip());

        return $this->show();
    }

    public function start(Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        $this->list->start($admin, $request->ip());

        return response()->json(['data' => $this->list->show()], 202);
    }

    public function test(Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => ['sentTo' => $this->list->test($admin, $request->ip())]], 202);
    }

    public function import(ImportMudeoRequest $request, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json(['data' => $this->list->import((string) $file->getRealPath(), $admin, $request->ip())]);
    }

    public function next(): JsonResponse
    {
        return response()->json(['data' => $this->list->next()]);
    }

    public function send(SendMudeoBatchRequest $request, #[CurrentUser] User $admin): JsonResponse
    {
        $ids = array_values(array_map(strval(...), (array) $request->validated('ids')));
        $this->list->send($ids, $admin, $request->ip());

        return response()->json(['data' => $this->list->show()], 201);
    }

    public function undo(Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        $this->list->undo($admin, $request->ip());

        return $this->show();
    }

    public function mark(MarkMudeoRequest $request, #[CurrentUser] User $admin): JsonResponse
    {
        $emails = array_values(array_map(strval(...), (array) $request->validated('emails')));

        return response()->json(['data' => $this->list->mark($emails, $request->validated('as') === 'bounced' ? 'bounced' : 'opted_out', $admin, $request->ip())]);
    }

    public function contacts(SearchMudeoRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->list->search((string) $request->validated('q'))]);
    }
}
