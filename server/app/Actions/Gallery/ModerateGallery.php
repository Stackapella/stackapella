<?php

declare(strict_types=1);

namespace App\Actions\Gallery;

use App\Actions\Notices\Notices;
use App\Actions\Sharing\ModerateHarmony;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Harmony;
use App\Models\Notice;
use App\Models\Report;
use App\Models\User;
use App\Support\Gallery\Bell;
use Illuminate\Support\Facades\DB;

/**
 * The gallery's review (N1): a level-2 singer's first harmonies in the gallery and comments the
 * word lists held wait here. Approved, they show; hidden, the person gets a statement of reasons
 * with a way to appeal (the DSA's notices, as any hide). Hillel features harmonies here too.
 */
final class ModerateGallery
{
    public function __construct(private readonly ModerateHarmony $harmonies, private readonly Notices $notices) {}

    public function decideHarmony(Harmony $harmony, User $admin, bool $approve, string $note, ?string $ip): void
    {
        if ($harmony->gallery_held_at === null) {
            throw new Refusal(409, 'not_held');
        }

        if ($approve) {
            $harmony->forceFill(['gallery_held_at' => null])->save();
            AuditLog::record('gallery.harmony_approved', $harmony, $admin, reason: $note, ip: $ip);

            return;
        }

        $this->harmonies->hide($harmony, $admin, Harmony::HIDDEN_ADMIN, $note, $ip);
        $harmony->refresh()->forceFill(['gallery_held_at' => null, 'gallery_opt_in_at' => null])->save();
    }

    public function decideComment(Comment $comment, User $admin, bool $approve, string $note, ?string $ip): void
    {
        DB::transaction(function () use ($comment, $admin, $approve, $note, $ip): void {
            $locked = Comment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();

            if ($approve) {
                if ($locked->held_at === null) {
                    throw new Refusal(409, 'not_held');
                }

                $locked->forceFill(['held_at' => null])->save();
                // Its reports are answered, so it takes new people reporting it to hold it again.
                Report::query()->whereMorphedTo('reportable', $locked)->where('status', Report::OPEN)->update(['status' => Report::DISMISSED, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);
                Harmony::query()->whereKey($locked->harmony_id)->increment('comments_count');
                AuditLog::record('gallery.comment_approved', $locked->harmony, $admin, reason: $note, ip: $ip);
                Bell::tell((int) Harmony::query()->whereKey($locked->harmony_id)->value('user_id'), 'comment', $locked->user);

                return;
            }

            if ($locked->hidden_at !== null) {
                return;
            }

            $shown = $locked->held_at === null;
            $locked->forceFill(['hidden_at' => now(), 'held_at' => null])->save();
            Report::query()->whereMorphedTo('reportable', $locked)->where('status', Report::OPEN)->update(['status' => Report::UPHELD, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);
            $shown && Harmony::query()->whereKey($locked->harmony_id)->where('comments_count', '>', 0)->decrement('comments_count');
            AuditLog::record('gallery.comment_hidden', $locked->harmony, $admin, reason: $note, ip: $ip);

            if ($locked->user !== null) {
                $this->notices->give($locked->user, Notice::COMMENT_HIDDEN, $locked, 'community_guidelines', $note);
            }
        });
    }

    /**
     * An appeal reversed: the comment shows again.
     */
    public function showComment(Comment $comment): void
    {
        if (Comment::query()->whereKey($comment->id)->whereNotNull('hidden_at')->update(['hidden_at' => null, 'updated_at' => now()]) > 0) {
            Harmony::query()->whereKey($comment->harmony_id)->increment('comments_count');
        }
    }

    public function feature(Harmony $harmony, User $admin, bool $featured, ?string $ip): Harmony
    {
        $harmony->forceFill(['featured_at' => $featured ? ($harmony->featured_at ?? now()) : null])->save();
        AuditLog::record($featured ? 'gallery.featured' : 'gallery.unfeatured', $harmony, $admin, ip: $ip);

        if ($featured) {
            Bell::tell($harmony->user_id, 'featured', null, $harmony);
        }

        return $harmony;
    }
}
