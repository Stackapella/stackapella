<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Services\GitHub\CodeSearch;
use App\Support\CodeWatch\CodeWatch;
use App\Support\Ids;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin > Code watch (docs/code-watch-plan.md): repositories holding our markers and other sites
 * calling our API, each with Dismiss and a note. Hillel decides what to do; nothing contacts anyone.
 */
final class CodeWatchController extends Controller
{
    public function __construct(private readonly CodeWatch $watch) {}

    public function index(CodeSearch $search): JsonResponse
    {
        return response()->json(['data' => [
            'hits' => $this->watch->hits(),
            'setup' => ['markers' => count((array) config('codewatch.markers')), 'search' => $search->configured()],
        ]]);
    }

    public function update(Request $request, string $hit): JsonResponse
    {
        $request->validate(['dismissed' => ['sometimes', 'boolean'], 'note' => ['sometimes', 'nullable', 'string', 'max:1000']]);
        $note = $request->input('note');
        $view = $this->watch->update(Ids::decode($hit) ?? 0, $request->has('dismissed') ? $request->boolean('dismissed') : null, $request->has('note'), is_string($note) ? $note : null);

        return response()->json(['data' => $view ?? throw new Refusal(404, 'not_found')]);
    }
}
