<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coaching;

use App\Actions\Coaching\CoachSearch;
use App\Actions\Coaching\Lessons;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\CoachesListRequest;
use App\Http\Requests\Coaching\ListSlotsRequest;
use App\Models\CoachProfile;
use App\Models\ExercisePack;
use App\Models\PackPurchase;
use App\Models\User;
use App\Support\Coaching\CoachDirectory;
use App\Support\Coaching\CoachingView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * coaches.show (a coach's public profile, by their link), coaches.slots (a lesson's free times) and
 * packs.mine.
 */
final class CoachesController extends Controller
{
    /**
     * coaches.list: the directory (N4), a page at a time.
     */
    public function index(CoachesListRequest $request, CoachSearch $search): JsonResponse
    {
        $viewer = $request->user() instanceof User ? $request->user() : null;
        /** @var array{voiceType?: string|null, style?: string|null, language?: string|null, maxPrice?: int|string|null, currency?: string|null, turnaround?: int|string|null, lessons?: bool|string|null, rating?: int|string|null, song?: string|null, sort?: string|null, tab?: string|null, cursor?: string|null} $filters */
        $filters = $request->validated();
        $page = $search->search($viewer, $filters);

        return response()->json([
            'data' => array_values(array_map(static fn (CoachProfile $p): array => CoachSearch::item($p), $page->items())),
            'meta' => ['nextCursor' => $page->hasMorePages() ? (string) ($page->currentPage() + 1) : null, ...$search->facets($viewer)],
        ]);
    }

    public function show(string $handle): JsonResponse
    {
        return response()->json(['data' => CoachDirectory::publicCoach($handle)]);
    }

    public function slots(ListSlotsRequest $request, string $handle, string $offering, Lessons $lessons): JsonResponse
    {
        $found = Lessons::offering($handle, $offering);
        $from = $request->validated('from');
        $timezone = $request->validated('timezone');

        return response()->json(['data' => $lessons->slots($found, is_string($from) ? $from : null, (int) ($request->validated('days') ?? 14), is_string($timezone) ? $timezone : 'UTC')]);
    }

    public function myPacks(#[CurrentUser] User $user): JsonResponse
    {
        $packs = ExercisePack::query()->with('coach')->whereIn('id', PackPurchase::query()->where('user_id', $user->id)->select('pack_id'))->orderBy('id')->get();

        return response()->json(['data' => $packs->map(fn (ExercisePack $pack): array => CoachingView::approvedPack($pack))->all()]);
    }
}
