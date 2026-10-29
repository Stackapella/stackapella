<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coaching;

use App\Actions\Coaching\Conversations;
use App\Actions\Coaching\Engagements;
use App\Actions\Coaching\Reviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\EngagementRequest;
use App\Http\Requests\Coaching\SendTakeRequest;
use App\Models\CoachMessage;
use App\Models\Engagement;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Coaching\CoachingView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * engagements.*, reviews.create, messages.* and ratings.create: buying from a coach and what follows.
 */
final class EngagementsController extends Controller
{
    public function __construct(private readonly Engagements $engagements) {}

    public function store(EngagementRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{offeringId?: string|null, packId?: string|null, takeId?: string|null, question?: string|null, pitchTrack?: array<string, mixed>|null, scores?: array<string, mixed>|null, adult?: bool|null, slot?: string|null, timezone?: string|null, notes?: string|null} $data */
        $data = $request->validated();
        $made = $this->engagements->create($user, $data);

        return response()->json(['data' => CoachingView::engagement($made['engagement']->load(['singer', 'coach', 'offering', 'rating', 'lesson']), $user), 'checkoutUrl' => $made['checkoutUrl']], 201);
    }

    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['as' => ['nullable', 'in:singer,coach'], 'cursor' => ['nullable', 'string', 'max:512']]);
        $coach = $request->query('as') === 'coach';

        $page = Engagement::query()->with(['singer', 'coach', 'offering', 'rating', 'lesson'])
            ->where($coach ? 'coach_id' : 'singer_id', $user->id)
            ->when($coach, fn ($query) => $query->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', now()->subDays((int) config('coaching.access_days')))))
            ->where('status', '!=', Engagement::PENDING_PAYMENT)
            ->orderByDesc('id')
            ->cursorPaginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Engagement $e): array => CoachingView::engagement($e, $user))->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function show(string $engagement, #[CurrentUser] User $user): JsonResponse
    {
        return $this->full($this->engagements->mine($user, $engagement, 'either'), $user);
    }

    public function accept(string $engagement, #[CurrentUser] User $user): JsonResponse
    {
        return $this->full($this->engagements->accept($user, $engagement), $user);
    }

    public function decline(Request $request, string $engagement, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $reason = $request->input('reason');

        return $this->full($this->engagements->decline($user, $engagement, is_string($reason) ? $reason : null), $user);
    }

    public function cancel(string $engagement, #[CurrentUser] User $user): JsonResponse
    {
        return $this->full($this->engagements->cancel($user, $engagement), $user);
    }

    public function report(Request $request, string $engagement, #[CurrentUser] User $user): Response
    {
        $request->validate(['text' => ['required', 'string', 'min:1', 'max:2000']]);
        $this->engagements->report($user, $engagement, (string) $request->input('text'));

        return response()->noContent();
    }

    public function sendTake(SendTakeRequest $request, string $engagement, Reviews $reviews, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{takeId?: string|null, question?: string|null, pitchTrack?: array<string, mixed>|null, scores?: array<string, mixed>|null} $data */
        $data = $request->validated();
        $review = $reviews->create($user, $engagement, $data);

        return response()->json(['data' => CoachingView::review($review->load(['engagement', 'media']), $user)], 201);
    }

    public function messages(Request $request, string $engagement, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:512']]);
        $found = $this->engagements->mine($user, $engagement, 'either');
        $page = CoachMessage::query()->with(['sender', 'media'])->where('engagement_id', $found->id)->orderBy('id')->cursorPaginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (CoachMessage $m): array => CoachingView::message($m))->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function message(Request $request, string $engagement, Conversations $conversations, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['text' => ['required_without:mediaId', 'nullable', 'string', 'min:1', 'max:2000'], 'mediaId' => ['nullable', 'uuid']]);
        $text = $request->input('text');
        $media = $request->input('mediaId');

        return response()->json(['data' => CoachingView::message($conversations->message($user, $engagement, is_string($text) ? $text : null, is_string($media) ? $media : null))], 201);
    }

    public function rate(Request $request, string $engagement, Conversations $conversations, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['stars' => ['required', 'integer', 'between:1,5'], 'text' => ['nullable', 'string', 'max:500']]);
        $text = $request->input('text');
        $rating = $conversations->rate($user, $engagement, (int) $request->input('stars'), is_string($text) ? $text : null);

        return response()->json(['data' => CoachingView::rating($rating->load('singer'))]);
    }

    private function full(Engagement $engagement, User $user): JsonResponse
    {
        $engagement->loadMissing(['singer', 'coach', 'offering', 'rating']);
        $reviews = ReviewItem::query()->with('media')->where('engagement_id', $engagement->id)->orderBy('id')->get();

        return response()->json(['data' => [
            'engagement' => CoachingView::engagement($engagement, $user),
            'reviews' => $reviews->map(fn (ReviewItem $r): array => CoachingView::review($r->setRelation('engagement', $engagement), $user))->values()->all(),
        ]]);
    }
}
