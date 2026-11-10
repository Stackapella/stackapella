<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Gallery;

use App\Actions\Gallery\Comments;
use App\Actions\Gallery\Explore;
use App\Actions\Gallery\Social;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gallery\CommentRequest;
use App\Http\Requests\Gallery\ExploreRequest;
use App\Models\Comment;
use App\Models\Harmony;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The gallery (N1; contract/api.md, "The gallery (N1)"): Explore, profiles, likes, follows,
 * blocks and comments. Closed (the `gallery` flag off), its reads find nothing and its writes
 * answer `409 gallery_closed`.
 */
final class GalleryController extends Controller
{
    public function __construct(private readonly Gallery $gallery, private readonly GalleryViews $views) {}

    public function explore(ExploreRequest $request, Explore $explore): JsonResponse
    {
        $this->ensureReadable();

        return response()->json($explore->list(self::viewer($request), (string) ($request->validated('tab') ?? 'new'), $request->validated('song'), $request->validated('q')));
    }

    public function profile(Request $request, string $handle): JsonResponse
    {
        $profile = $this->profileOf($handle, self::viewer($request));

        return response()->json(['data' => $this->views->profile($profile, self::viewer($request))]);
    }

    public function profileHarmonies(Request $request, string $handle, Explore $explore): JsonResponse
    {
        $viewer = self::viewer($request);
        $profile = $this->profileOf($handle, $viewer);

        return response()->json($explore->list($viewer, 'new', null, null, $profile->id));
    }

    public function like(string $harmony, Social $social, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $social->like($user, $this->harmony($harmony), true)]);
    }

    public function unlike(string $harmony, Social $social, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $social->like($user, $this->harmony($harmony), false)]);
    }

    public function follow(string $handle, Social $social, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $social->follow($user, $this->byHandle($handle), true)]);
    }

    public function unfollow(string $handle, Social $social, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $social->follow($user, $this->byHandle($handle), false)]);
    }

    /**
     * Only a public profile can be blocked by its handle, so a block can't confirm that a private
     * account's handle exists (unblocking works for anyone).
     */
    public function block(string $handle, Social $social, #[CurrentUser] User $user): Response
    {
        $other = $this->byHandle($handle);

        if (! Gallery::hasPublicProfile($other)) {
            throw new Refusal(404, 'not_found');
        }

        $social->block($user, $other, true);

        return response()->noContent();
    }

    public function unblock(string $handle, Social $social, #[CurrentUser] User $user): Response
    {
        $social->block($user, $this->byHandle($handle), false);

        return response()->noContent();
    }

    public function blocks(#[CurrentUser] User $user): JsonResponse
    {
        $rows = [];

        foreach (UserBlock::query()->with('blocked')->where('blocker_id', $user->id)->get() as $block) {
            $person = $block->blocked;

            if ($person === null) {
                continue;
            }

            // Someone without a public profile (any more) is shown as no one in particular, and is
            // unblocked by the row's own id (Hillel: "Make them unblockable").
            $rows[] = ['blockId' => $block->sqid(), ...(Gallery::hasPublicProfile($person) ? GalleryViews::person($person) : ['handle' => null, 'name' => __('sharing.a_singer'), 'avatarUrl' => null]), 'sort' => (string) $person->name];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));

        return response()->json(['data' => array_map(static fn (array $row): array => array_diff_key($row, ['sort' => true]), $rows)]);
    }

    /**
     * Unblock by the block itself: works for someone whose profile is no longer public.
     */
    public function unblockById(UserBlock $block, #[CurrentUser] User $user): Response
    {
        if ($block->blocker_id !== $user->id) {
            throw new Refusal(404, 'not_found');
        }

        $block->delete();

        return response()->noContent();
    }

    public function comments(Request $request, string $harmony, Comments $comments): JsonResponse
    {
        $this->ensureReadable();
        $viewer = self::viewer($request);
        $harmony = $this->harmony($harmony);

        if (! $this->gallery->isListed($harmony) || ($viewer !== null && $this->gallery->blockedBetween($viewer, $harmony->user_id))) {
            throw new Refusal(404, 'not_found');
        }

        $page = $comments->visible($harmony, $viewer)->orderByDesc('id')->cursorPaginate(30);

        return response()->json(['data' => array_values(array_map(fn (Comment $c): array => $this->views->comment($c, $viewer, $harmony), $page->items())), 'meta' => ['nextCursor' => $page->nextCursor()?->encode()]]);
    }

    public function comment(CommentRequest $request, string $harmony, Comments $comments, #[CurrentUser] User $user): JsonResponse
    {
        $harmony = $this->harmony($harmony);
        $made = $comments->create($user, $harmony, (string) $request->validated('body'));

        return response()->json(['data' => $this->views->comment($made->load('user'), $user, $harmony)], 201);
    }

    public function deleteComment(Comment $comment, Comments $comments, #[CurrentUser] User $user): Response
    {
        $comments->delete($user, $comment);

        return response()->noContent();
    }

    private function ensureReadable(): void
    {
        if (! $this->gallery->isOpen()) {
            throw new Refusal(404, 'not_found');
        }
    }

    private function profileOf(string $handle, ?User $viewer): User
    {
        $this->ensureReadable();
        $profile = User::query()->where('handle', strtolower($handle))->first();

        if ($profile === null || ! Gallery::hasPublicProfile($profile) || ($viewer !== null && $viewer->id !== $profile->id && $this->gallery->blockedBetween($viewer, $profile->id))) {
            throw new Refusal(404, 'not_found');
        }

        return $profile;
    }

    private function harmony(string $publicId): Harmony
    {
        return Harmony::query()->where('public_id', $publicId)->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found');
    }

    private function byHandle(string $handle): User
    {
        return User::query()->where('handle', strtolower($handle))->first() ?? throw new Refusal(404, 'not_found');
    }

    private static function viewer(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
