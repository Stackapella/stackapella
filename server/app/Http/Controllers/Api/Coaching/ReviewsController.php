<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coaching;

use App\Actions\Coaching\Conversations;
use App\Actions\Coaching\Reviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\MediaRequest;
use App\Http\Requests\Coaching\ReviewDraftRequest;
use App\Models\ReviewItem;
use App\Models\User;
use App\Support\Coaching\CoachingView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * reviews.*, reviews.media.* and ratings.reply: the review workspace.
 */
final class ReviewsController extends Controller
{
    public function __construct(private readonly Reviews $reviews) {}

    public function save(ReviewDraftRequest $request, string $review, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array<string, mixed> $draft */
        $draft = $request->validated();

        return $this->review($this->reviews->save($user, $review, $draft), $user);
    }

    public function deliver(string $review, #[CurrentUser] User $user): JsonResponse
    {
        return $this->review($this->reviews->deliver($user, $review), $user);
    }

    public function unsend(string $review, #[CurrentUser] User $user): JsonResponse
    {
        return $this->review($this->reviews->unsend($user, $review), $user);
    }

    public function followUp(Request $request, string $review, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['question' => ['required', 'string', 'min:1', 'max:1000']]);

        return $this->review($this->reviews->followUp($user, $review, (string) $request->input('question')), $user);
    }

    public function answer(Request $request, string $review, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['text' => ['required', 'string', 'min:1', 'max:1000'], 'mediaId' => ['nullable', 'uuid']]);
        $media = $request->input('mediaId');

        return $this->review($this->reviews->answer($user, $review, (string) $request->input('text'), is_string($media) ? $media : null), $user);
    }

    public function startMedia(MediaRequest $request, string $review, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{id: string, kind: string, sampleRate: int, frames: int, bytes: int, sha256: string} $data */
        $data = $request->validated();
        $started = $this->reviews->startMedia($user, $review, $data);
        $upload = $started['upload'];

        return response()->json([
            'data' => CoachingView::media($started['media']),
            'upload' => $upload !== null ? [...$upload, 'expiresAt' => Carbon::instance($upload['expiresAt'])->utc()->toIso8601ZuluString()] : null,
        ], 201);
    }

    public function completeMedia(string $media, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => CoachingView::media($this->reviews->completeMedia($user, $media))]);
    }

    public function reply(Request $request, string $rating, Conversations $conversations, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['text' => ['required', 'string', 'min:1', 'max:500']]);

        return response()->json(['data' => CoachingView::rating($conversations->reply($user, $rating, (string) $request->input('text'))->load('singer'))]);
    }

    private function review(ReviewItem $review, User $user): JsonResponse
    {
        return response()->json(['data' => CoachingView::review($review->load(['engagement', 'media']), $user)]);
    }
}
