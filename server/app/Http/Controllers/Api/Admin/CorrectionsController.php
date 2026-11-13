<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Corrections\DecideCorrection;
use App\Enums\CorrectionKind;
use App\Enums\CorrectionState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Corrections\UpdateCorrectionRequest;
use App\Http\Resources\Admin\CorrectionResource;
use App\Models\Correction;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin > Inbox > Corrections (docs/corrections-plan.md): readers' corrections, newest first, filtered by state and
 * kind, and the team's decision on each. Editors.
 */
final class CorrectionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'state' => ['nullable', Rule::enum(CorrectionState::class)],
            'kind' => ['nullable', Rule::enum(CorrectionKind::class)],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);
        $page = Correction::query()->with('decider')
            ->when($request->query('state'), fn ($q, $state) => $q->where('state', $state))
            ->when($request->query('kind'), fn ($q, $kind) => $q->where('kind', $kind))
            ->orderByDesc('id')->cursorPaginate(25);
        $counts = Correction::query()->selectRaw('state, count(*) as n')->groupBy('state')->pluck('n', 'state');

        return response()->json([
            'data' => CorrectionResource::collection($page->items())->resolve($request),
            'meta' => [
                'nextCursor' => $page->nextCursor()?->encode(),
                'counts' => array_combine(
                    array_map(static fn (CorrectionState $s): string => $s->value, CorrectionState::cases()),
                    array_map(static fn (CorrectionState $s): int => (int) ($counts[$s->value] ?? 0), CorrectionState::cases()),
                ),
            ],
        ]);
    }

    public function update(UpdateCorrectionRequest $request, string $correction, DecideCorrection $decide, #[CurrentUser] User $user): JsonResponse
    {
        $decided = $decide->handle($user, $correction, CorrectionState::from((string) $request->validated('state')), $request->validated('note'));

        return response()->json(['data' => (new CorrectionResource($decided))->resolve($request)]);
    }
}
