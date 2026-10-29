<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSocialPostsRequest;
use App\Models\Harmony;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\Social\SocialPoster;
use App\Support\Gallery\Gallery;
use App\Support\Ids;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * admin.social.*: Hillel approves (or turns down) each of our own posts, undoes one within its
 * window, tries a failed one again, and features a harmony its singer agreed to.
 */
final class SocialController extends Controller
{
    public function __construct(private readonly SocialPoster $poster) {}

    public function index(ListSocialPostsRequest $request): JsonResponse
    {
        $status = $request->validated('status') ?? SocialPost::AWAITING;
        // What can't go any more leaves the waiting list first.
        $this->poster->tidy();
        $page = SocialPost::query()->with('postable')->when($status !== 'all', fn ($query) => $query->where('status', $status))->orderByDesc('id')->cursorPaginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (SocialPost $post): array => $this->poster->view($post))->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
            'networks' => array_keys($this->poster->networks()),
        ]);
    }

    public function approve(string $post, #[CurrentUser] User $admin): JsonResponse
    {
        $found = $this->find($post);

        if ($found->status !== SocialPost::AWAITING) {
            throw new Refusal(409, 'post_state');
        }

        if ($this->poster->message($found, media: false) === null) {
            $this->poster->cancel($found);

            throw new Refusal(409, 'post_gone');
        }

        return $this->one($this->poster->approve($found, $admin));
    }

    public function cancel(Request $request, string $post, #[CurrentUser] User $admin): JsonResponse
    {
        $found = $this->find($post);

        if (! in_array($found->status, [SocialPost::AWAITING, SocialPost::QUEUED], true)) {
            throw new Refusal(409, 'post_state');
        }

        return $this->one($this->poster->cancel($found, $admin, $request->ip()));
    }

    public function retry(Request $request, string $post, #[CurrentUser] User $admin): JsonResponse
    {
        $found = $this->find($post);

        if ($found->status !== SocialPost::FAILED) {
            throw new Refusal(409, 'post_state');
        }

        return $this->one($this->poster->retry($found, $admin, $request->ip()));
    }

    public function feature(string $harmony): JsonResponse
    {
        $found = Harmony::query()->with('user')->where('public_id', $harmony)->first() ?? throw new Refusal(404, 'not_found');

        // The yes, and a harmony that's public by the gallery's rule (18 and over, every friend's yes).
        if ($found->may_feature_at === null || ! app(Gallery::class)->isPublic($found)) {
            throw new Refusal(409, 'feature_not_allowed');
        }

        return response()->json(['data' => $this->poster->feature($found)->map(fn (SocialPost $post): array => $this->poster->view($post))->values()->all()], 201);
    }

    private function find(string $sqid): SocialPost
    {
        return SocialPost::query()->with('postable')->whereKey(Ids::decode($sqid) ?? 0)->first() ?? throw new Refusal(404, 'not_found');
    }

    private function one(SocialPost $post): JsonResponse
    {
        return response()->json(['data' => $this->poster->view($post->load('postable'))]);
    }
}
