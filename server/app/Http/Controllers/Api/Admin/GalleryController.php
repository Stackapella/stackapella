<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Gallery\GalleryGuides;
use App\Actions\Gallery\ModerateGallery;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Gallery\DecideHeldRequest;
use App\Http\Requests\Admin\Gallery\FeatureRequest;
use App\Http\Requests\Admin\Gallery\PublishGuideRequest;
use App\Models\Comment;
use App\Models\Harmony;
use App\Models\User;
use App\Support\Gallery\GalleryViews;
use App\Support\Ids;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.gallery.* and admin.harmonies.feature (N1): what waits for review, and what's featured.
 */
final class GalleryController extends Controller
{
    public function __construct(private readonly GalleryViews $views) {}

    public function held(): JsonResponse
    {
        $harmonies = Harmony::query()->with(GalleryViews::WITH)->whereNotNull('gallery_held_at')->whereNotNull('gallery_opt_in_at')->whereNull('deleted_at')->orderBy('gallery_held_at')->limit(100)->get();
        $comments = Comment::query()->with(['user', 'harmony'])->whereNotNull('held_at')->whereNull('hidden_at')->orderBy('id')->limit(100)->get();

        return response()->json(['data' => [
            'harmonies' => $this->views->harmonies($harmonies, null),
            'comments' => array_values($comments->map(fn (Comment $c): array => [
                'id' => $c->sqid(),
                'body' => $c->body,
                'author' => [...GalleryViews::person($c->user), 'id' => $c->user?->sqid()],
                'harmony' => ['id' => $c->harmony?->public_id, 'title' => $c->harmony?->title],
                'createdAt' => $c->created_at?->utc()->toIso8601ZuluString(),
            ])->all()),
        ]]);
    }

    public function decide(DecideHeldRequest $request, string $kind, string $id, ModerateGallery $moderate, #[CurrentUser] User $admin): JsonResponse
    {
        $approve = $request->validated('decision') === 'approve';
        $note = (string) ($request->validated('note') ?? '');

        match ($kind) {
            'harmony' => $moderate->decideHarmony(Harmony::query()->where('public_id', $id)->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found'), $admin, $approve, $note, $request->ip()),
            'comment' => $moderate->decideComment(Comment::query()->find(Ids::decode($id) ?? 0) ?? throw new Refusal(404, 'not_found'), $admin, $approve, $note, $request->ip()),
            default => throw new Refusal(404, 'not_found'),
        };

        return $this->held();
    }

    public function guide(PublishGuideRequest $request, string $harmony, GalleryGuides $guides, #[CurrentUser] User $admin): JsonResponse
    {
        $found = Harmony::query()->with(['templateVersion.template', 'user'])->where('public_id', $harmony)->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found');
        $guides->publish($admin, $found, (string) $request->validated('partId'), $request->boolean('makeDefault'), $request->ip());

        return response()->json(['data' => ['queued' => true]], 202);
    }

    public function feature(FeatureRequest $request, string $harmony, ModerateGallery $moderate, #[CurrentUser] User $admin): JsonResponse
    {
        $found = Harmony::query()->with(GalleryViews::WITH)->where('public_id', $harmony)->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found');
        $moderate->feature($found, $admin, $request->boolean('featured'), $request->ip());

        return response()->json(['data' => $this->views->harmony($found->refresh()->load(GalleryViews::WITH), null, false)]);
    }
}
