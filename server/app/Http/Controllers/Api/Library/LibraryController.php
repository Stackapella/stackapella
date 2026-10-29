<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Library;

use App\Actions\Templates\Library;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\LibraryListRequest;
use App\Http\Requests\Library\PublishTemplateRequest;
use App\Http\Requests\Library\RateTemplateRequest;
use App\Http\Resources\Templates\LibraryItemResource;
use App\Http\Resources\Templates\OwnTemplateResource;
use App\Models\Template;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * library.list, templates.publish, .unpublish, .rate and .unrate: the template library (N3).
 */
final class LibraryController extends Controller
{
    public function __construct(private readonly Library $library) {}

    public function index(LibraryListRequest $request): JsonResponse
    {
        $viewer = $request->user() instanceof User ? $request->user() : null;
        /** @var array{q?: string|null, difficulty?: string|null, parts?: int|string|null, genre?: string|null, key?: string|null, tempo?: string|null, low?: int|string|null, high?: int|string|null, creator?: string|null, sort?: string|null, tab?: string|null} $filters */
        $filters = $request->safe()->except('cursor');
        $page = $this->library->search($viewer, $filters);
        $ratings = Library::ratingsOf($viewer, array_values(array_map(static fn (Template $t): int => $t->id, $page->items())));

        return response()->json([
            'data' => array_map(static fn (Template $t): array => LibraryItemResource::of($t, $viewer, $ratings), $page->items()),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode(), 'genres' => $this->library->genres($viewer)],
        ]);
    }

    public function publish(PublishTemplateRequest $request, string $template, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{song: string, source?: string|null, ownWork: bool} $declaration */
        $declaration = $request->validated();

        return self::own($this->library->publish($user, $template, $declaration, $request->ip()));
    }

    public function unpublish(Request $request, string $template, #[CurrentUser] User $user): JsonResponse
    {
        return self::own($this->library->unpublish($user, $template, $request->ip()));
    }

    public function rate(RateTemplateRequest $request, string $template, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->library->rate($user, $template, (int) $request->validated('stars'))]);
    }

    public function unrate(string $template, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->library->rate($user, $template, null)]);
    }

    private static function own(Template $template): JsonResponse
    {
        return (new OwnTemplateResource($template->load(['versions:id,template_id,version', 'recordings', 'owner'])))->response()->setStatusCode(200);
    }
}
