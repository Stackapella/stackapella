<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Actions\Gallery\Consents;
use App\Exceptions\Refusal;
use App\Models\Harmony;
use App\Models\User;
use App\Services\Social\SocialPoster;
use App\Support\Gallery\Gallery;
use App\Support\Moderation\TextFilter;
use App\Support\Sharing\HarmonyFiles;
use App\Support\Trust\TrustLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * harmonies.update: a new title (held again if the word lists match it), or who can hear it.
 * Becoming unlisted counts as a share; a moderated harmony can't. `mayFeature` is the owner's yes
 * (or no) to our accounts featuring it (M10): a no cancels any post not yet sent. Neither that
 * yes nor the gallery's is the owner's alone when a friend sang on it (`409 singers_must_agree`).
 */
final class UpdateHarmony
{
    public function __construct(private readonly ShareGate $gate, private readonly HarmonyFiles $files, private readonly Gallery $gallery, private readonly TrustLevel $trust) {}

    /**
     * @param  array{title?: string|null, visibility?: string, mayFeature?: bool, inGallery?: bool, featuredFillId?: string|null}  $data
     */
    public function handle(Harmony $harmony, array $data, string $ip): Harmony
    {
        $renamed = DB::transaction(function () use ($harmony, $data, $ip): bool {
            // The account first, as every change that counts toward a limit: two at once count in turn.
            User::query()->whereKey($harmony->user_id)->lockForUpdate()->first(['id']);
            $locked = Harmony::query()->with('user')->whereKey($harmony->id)->lockForUpdate()->firstOrFail();
            $renamed = false;

            if (array_key_exists('title', $data)) {
                $title = trim((string) $data['title']);

                if ($title !== $locked->title) {
                    $locked->forceFill(['title' => $title, 'title_held_at' => TextFilter::holds($title) ? now() : null]);
                    $renamed = true;
                }
            }

            $visibility = $data['visibility'] ?? $locked->visibility;

            if ($visibility === Harmony::UNLISTED && $locked->visibility !== Harmony::UNLISTED) {
                if ($locked->moderation() !== null) {
                    throw new Refusal(409, 'harmony_unavailable');
                }

                // A failed mix has nothing to share.
                if ($locked->status === Harmony::FAILED) {
                    throw new Refusal(409, 'harmony_not_ready');
                }

                $this->gate->mayShare($locked->user, $ip);
            }

            if ((($data['inGallery'] ?? false) || ($data['mayFeature'] ?? false)) && ! Consents::allAgreed($locked)) {
                throw new Refusal(409, 'singers_must_agree');
            }

            if (($data['rightsConfirmed'] ?? false) === true) {
                $locked->forceFill(['rights_confirmed_at' => $locked->rights_confirmed_at ?? now()]);
            }

            if (array_key_exists('commentsOff', $data)) {
                $locked->forceFill(['comments_off_at' => $data['commentsOff'] ? ($locked->comments_off_at ?? now()) : null]);
            }

            // In the gallery (N1). Before it opens, only the opt-in is kept; once open, the owner
            // needs a public profile, 18 or over and trust level 2, and to have said they have the
            // right to share the song. A level-2 singer's first two wait for review. One opted in
            // before it opened waits for review unless its owner is trusted, since none of that
            // was asked; and it isn't listed until they confirm the right to share it.
            if (array_key_exists('inGallery', $data)) {
                if ($data['inGallery'] && $locked->gallery_opt_in_at === null) {
                    if ($this->gallery->isOpen()) {
                        $this->ensureMayList($locked);
                    }

                    $held = $this->gallery->isOpen() ? $this->gallery->postsHeld($locked->user) : $this->trust->of($locked->user) < TrustLevel::TRUSTED;
                    $locked->forceFill(['gallery_held_at' => $held ? now() : null]);
                }

                $locked->forceFill(['gallery_opt_in_at' => $data['inGallery'] ? ($locked->gallery_opt_in_at ?? now()) : null]);
            }

            if (array_key_exists('mayFeature', $data)) {
                // Featured on our accounts is public: 18 and over, as the gallery is.
                if ($data['mayFeature']) {
                    Gallery::ensureAdult($locked->user);
                }

                $locked->forceFill(['may_feature_at' => $data['mayFeature'] ? ($locked->may_feature_at ?? now()) : null]);

                if (! $data['mayFeature']) {
                    SocialPoster::withdraw($locked);
                }
            }

            // Open parts (N5): the fill its owner features on its page (a public one sung for it).
            if (array_key_exists('featuredFillId', $data)) {
                $fill = $data['featuredFillId'] !== null ? Harmony::query()->where('public_id', $data['featuredFillId'])->where('parent_id', $locked->id)->whereNull('deleted_at')->first() : null;

                if ($data['featuredFillId'] !== null && ($fill === null || ! $this->gallery->isPublic($fill))) {
                    throw ValidationException::withMessages(['featuredFillId' => __('validation.featured_fill')]);
                }

                $locked->forceFill(['featured_fill_id' => $fill?->id]);
            }

            $locked->forceFill(['visibility' => $visibility])->save();

            return $renamed;
        });

        // Both read the row afresh, and leave the harmony as saved. A new title needs a new card.
        $renamed ? $this->files->redrawCard($harmony) : $this->files->sync($harmony);

        return $harmony;
    }

    private function ensureMayList(Harmony $harmony): void
    {
        $owner = $harmony->user;
        Gallery::ensureAdult($owner);

        if ($this->trust->of($owner) < TrustLevel::SINGER) {
            throw new Refusal(403, 'trust_required');
        }

        // A public profile, or a hidden one (shown as Anonymous).
        if (! Gallery::mayShowWork($owner)) {
            throw new Refusal(409, 'profile_required');
        }

        if ($harmony->rights_confirmed_at === null) {
            throw ValidationException::withMessages(['rightsConfirmed' => __('validation.rights_confirmed')]);
        }
    }
}
