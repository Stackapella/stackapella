<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Coaching\Applications;
use App\Actions\Coaching\CoachSetup;
use App\Actions\Coaching\Engagements;
use App\Exceptions\Refusal;
use App\Http\Controllers\Api\Coaching\CoachController;
use App\Http\Controllers\Controller;
use App\Models\CoachApplication;
use App\Models\CoachProfile;
use App\Models\Engagement;
use App\Models\EngagementReport;
use App\Models\ExercisePack;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Coaching\CoachingView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * admin.coaching.*: applications, coaches' status, packs' review, disputes and refunds.
 */
final class CoachingController extends Controller
{
    public function applications(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:submitted,approved,declined'], 'cursor' => ['nullable', 'string', 'max:512']]);
        $status = (string) ($request->query('status') ?? CoachApplication::SUBMITTED);
        $page = CoachApplication::query()->with('user')->where('status', $status)->orderBy($status === CoachApplication::SUBMITTED ? 'id' : 'decided_at', $status === CoachApplication::SUBMITTED ? 'asc' : 'desc')->orderBy('id')->cursorPaginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (CoachApplication $a): array => self::application($a))->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function decide(Request $request, string $application, Applications $applications, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['decision' => ['required', 'in:approve,decline'], 'note' => ['nullable', 'string', 'max:1000']]);
        $found = CoachApplication::query()->whereSqid($application)->first() ?? throw new Refusal(404, 'not_found');
        $note = $request->input('note');
        $decided = $applications->decide($found, $admin, $request->input('decision') === 'approve', is_string($note) ? $note : null, $request->ip());

        return response()->json(['data' => self::application($decided->load('user'))]);
    }

    public function coach(Request $request, string $coach, CoachSetup $setup, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:'.CoachProfile::APPROVED.','.CoachProfile::PAUSED.','.CoachProfile::SUSPENDED], 'note' => ['required', 'string', 'min:1', 'max:1000']]);

        return response()->json(['data' => CoachingView::profile($setup->setStatus($coach, $admin, (string) $request->input('status'), (string) $request->input('note'), $request->ip())->load('user'))]);
    }

    public function packs(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:submitted,approved,rejected']]);
        $packs = ExercisePack::query()->with('coach')->where('status', (string) ($request->query('status') ?? ExercisePack::SUBMITTED))->orderBy('updated_at')->limit(200)->get();

        return response()->json(['data' => $packs->map(fn (ExercisePack $p): array => CoachingView::pack($p))->all()]);
    }

    public function decidePack(Request $request, string $pack, CoachSetup $setup, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:1000']]);
        $found = ExercisePack::query()->with('coach')->where('uuid', $pack)->first() ?? throw new Refusal(404, 'not_found');
        $note = $request->input('note');

        return response()->json(['data' => CoachingView::pack($setup->decidePack($found, $admin, $request->input('decision') === 'approve', is_string($note) ? $note : null, $request->ip()))]);
    }

    public function disputes(Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:512']]);
        $page = Engagement::query()->with(['singer', 'coach', 'offering', 'rating'])
            ->whereHas('reports')
            ->withMin(['reports as open_since' => fn ($query) => $query->whereNull('resolved_at')], 'created_at')
            ->orderByRaw('open_since is null')->orderBy('id')
            ->cursorPaginate(25);
        $reports = EngagementReport::query()->with('user')->whereIn('engagement_id', collect($page->items())->pluck('id'))->orderBy('id')->get()->groupBy('engagement_id');

        return response()->json([
            'data' => collect($page->items())->map(fn (Engagement $e): array => [
                // Hillel sees the money, as the singer does.
                'engagement' => CoachingView::engagement($e, null),
                'reports' => ($reports->get($e->id) ?? collect())->map(fn (EngagementReport $r): array => ['from' => CoachingView::person($r->user), 'text' => $r->text, 'createdAt' => $r->created_at?->utc()->toIso8601ZuluString(), 'resolvedAt' => $r->resolved_at?->utc()->toIso8601ZuluString()])->values()->all(),
            ])->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function refund(Request $request, string $engagement, Engagements $engagements, #[CurrentUser] User $admin): JsonResponse
    {
        $request->validate(['amountCents' => ['required', 'integer', 'min:1'], 'note' => ['required', 'string', 'min:1', 'max:1000']]);
        $refunded = $engagements->adminRefund($engagement, $admin, (int) $request->input('amountCents'), (string) $request->input('note'), $request->ip());
        $refunded->load(['singer', 'coach', 'offering', 'rating']);
        $reviews = ReviewItem::query()->with('media')->where('engagement_id', $refunded->id)->orderBy('id')->get();

        return response()->json(['data' => [
            'engagement' => CoachingView::engagement($refunded, null),
            'reviews' => $reviews->map(fn (ReviewItem $r): array => CoachingView::review($r->setRelation('engagement', $refunded), null))->values()->all(),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function application(CoachApplication $application): array
    {
        return ['application' => CoachController::applicationFields($application), 'person' => ['id' => $application->user->sqid(), 'name' => $application->user->name]];
    }
}
