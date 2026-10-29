<?php

declare(strict_types=1);

namespace App\Actions\Gallery;

use App\Exceptions\Refusal;
use App\Models\Comment;
use App\Models\Harmony;
use App\Models\User;
use App\Support\Gallery\Bell;
use App\Support\Gallery\Gallery;
use App\Support\Moderation\TextFilter;
use App\Support\Trust\TrustLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Comments on a harmony in the gallery (N1): plain text, 500 characters, no links. One the word
 * lists match is held, seen only by its author until Hillel looks; its author or the harmony's
 * owner may delete it; an owner may turn comments off. Blocks keep the two apart.
 */
final class Comments
{
    public function __construct(private readonly Gallery $gallery, private readonly TrustLevel $trust) {}

    public function create(User $user, Harmony $harmony, string $body): Comment
    {
        $this->gallery->ensureOpen();
        $body = trim($body);

        if (! $this->gallery->isListed($harmony)) {
            throw new Refusal(404, 'not_found');
        }

        if ($harmony->comments_off_at !== null) {
            throw new Refusal(409, 'comments_off');
        }

        Gallery::ensureAdult($user);

        if ($this->trust->of($user) < TrustLevel::SINGER) {
            throw new Refusal(403, 'trust_required');
        }

        if ($this->gallery->blockedBetween($user, $harmony->user_id)) {
            throw new Refusal(403, 'blocked');
        }

        if (TextFilter::hasLink($body)) {
            throw ValidationException::withMessages(['body' => __('validation.no_links')]);
        }

        $comment = DB::transaction(function () use ($user, $harmony, $body): Comment {
            $comment = new Comment(['body' => $body]);
            $comment->forceFill(['harmony_id' => $harmony->id, 'user_id' => $user->id, 'held_at' => TextFilter::holds($body) ? now() : null])->save();

            if ($comment->held_at === null) {
                Harmony::query()->whereKey($harmony->id)->increment('comments_count');
            }

            return $comment;
        });

        if ($comment->held_at === null) {
            Bell::tell($harmony->user_id, 'comment', $user, $harmony);
        }

        return $comment;
    }

    public function delete(User $user, Comment $comment): void
    {
        $harmony = Harmony::query()->findOrFail($comment->harmony_id);

        if (! in_array($user->id, [$comment->user_id, $harmony->user_id], true)) {
            throw new Refusal(404, 'not_found');
        }

        DB::transaction(function () use ($comment, $harmony): void {
            if (Comment::query()->whereKey($comment->id)->delete() > 0 && $comment->held_at === null && $comment->hidden_at === null) {
                Harmony::query()->whereKey($harmony->id)->where('comments_count', '>', 0)->decrement('comments_count');
            }
        });
    }

    /**
     * The comments someone sees: shown ones, their own held ones, nobody they're blocked with.
     *
     * @return Builder<Comment>
     */
    public function visible(Harmony $harmony, ?User $viewer): Builder
    {
        $blocked = $viewer !== null ? $this->gallery->blockedIds($viewer) : [];

        return Comment::query()->with('user')->where('harmony_id', $harmony->id)->whereNull('hidden_at')
            ->where(fn ($query) => $query->whereNull('held_at')->when($viewer !== null, fn ($q) => $q->orWhere('user_id', $viewer?->id)))
            ->when($blocked !== [], fn ($query) => $query->whereNotIn('user_id', $blocked));
    }
}
