<?php

declare(strict_types=1);

namespace App\Support\Sharing;

use App\Actions\Gallery\GalleryGuides;
use App\Actions\Gallery\OpenParts;
use App\Jobs\PurgeCdn;
use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Models\TemplateGuide;
use App\Services\Sharing\ShareCard;
use App\Services\Social\SocialPoster;
use App\Support\Gallery\Bell;
use App\Support\Gallery\Gallery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A harmony's files: the AAC and the card live in `private`, and a copy of each sits on the public
 * `media` bucket (behind the CDN) only while its page is live. Going private, hidden, removed or
 * deleted takes the copies off the bucket and the CDN's edge at once; coming back makes new ones
 * under new random keys, so an old address never plays again. The keys are forgotten only once the
 * files are gone, so a failed delete is tried again by the next sync, never left public unknown.
 */
final class HarmonyFiles
{
    private const CACHE = 'public, max-age=31536000, immutable';

    public function __construct(private readonly ShareCard $card, private readonly HarmonyFacts $facts) {}

    /**
     * Make the public copies match whether the page is live.
     */
    public function sync(Harmony $harmony): void
    {
        DB::transaction(function () use ($harmony): void {
            $locked = Harmony::query()->with('user')->whereKey($harmony->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $live = $locked->isLive() && $locked->audio_key !== null && $locked->card_key !== null;

            if (! $live && $locked->media_audio_key !== null) {
                $this->unpublish($locked);
            }

            if ($live && $locked->media_audio_key === null) {
                $this->publish($locked);
            }

            $harmony->setRawAttributes($locked->getAttributes(), true);
            // Its owner as just read, for the checks below (never read again for each).
            $harmony->setRelation('user', $locked->user);
        });

        $this->dropGuidesUnlessPublic($harmony);
        $this->tellOriginal($harmony);
    }

    /**
     * A song's guide voices go as soon as it isn't public any more (not featured, nor in the
     * gallery, or a friend's yes withdrawn), and so do its fills (N5): a fill carries its
     * original's voices, so it's made private with it.
     */
    public function dropGuidesUnlessPublic(Harmony $harmony): void
    {
        $public = app(Gallery::class)->isPublic($harmony);

        if (! $public && TemplateGuide::query()->where('harmony_id', $harmony->id)->exists()) {
            app(GalleryGuides::class)->removeFor($harmony, null, $harmony->hidden_at !== null ? 'hidden' : 'not_public');
        }

        if (! $public) {
            $this->closeFills($harmony);
        }
    }

    /**
     * Its fills made private, out of the gallery and featuring (and theirs in turn): they carry
     * voices that are no longer public.
     */
    public function closeFills(Harmony $harmony): void
    {
        foreach (Harmony::query()->where('parent_id', $harmony->id)->where('visibility', Harmony::UNLISTED)->whereNull('deleted_at')->get() as $fill) {
            $fill->forceFill(['visibility' => Harmony::PRIVATE, 'gallery_opt_in_at' => null, 'may_feature_at' => null])->save();
            SocialPoster::withdraw($fill);
            $this->sync($fill);
        }
    }

    /**
     * A fill gone public (N5): its original's owner hears of it, once.
     */
    private function tellOriginal(Harmony $harmony): void
    {
        if ($harmony->parent_id === null || $harmony->fill_told_at !== null || ! app(Gallery::class)->isPublic($harmony)) {
            return;
        }

        $parent = Harmony::query()->whereKey($harmony->parent_id)->whereNull('deleted_at')->first(['id', 'user_id']);

        if ($parent !== null && Harmony::query()->whereKey($harmony->id)->whereNull('fill_told_at')->update(['fill_told_at' => now()]) === 1) {
            Bell::tell($parent->user_id, 'fill', $harmony->user, $parent);
        }
    }

    /**
     * The share card again, after the title changes or a held title is released: a new card
     * (and, while live, new public copies, so the CDN never serves the old one).
     */
    public function redrawCard(Harmony $harmony): void
    {
        DB::transaction(function () use ($harmony): void {
            $locked = Harmony::query()->with('user', 'templateVersion.template')->whereKey($harmony->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== Harmony::READY || $locked->card_key === null) {
                return;
            }

            $old = $locked->card_key;
            $key = 'harmonies/'.Str::lower(Str::random(32)).'.jpg';
            Storage::disk('private')->put($key, $this->card->render($this->facts->title($locked), $this->facts->singersLine($locked), $this->facts->songLine($locked), $locked->tracks));
            $locked->forceFill(['card_key' => $key])->save();
            Storage::disk('private')->delete($old);

            if ($locked->media_audio_key !== null) {
                $this->unpublish($locked);
            }

            // Live (perhaps only just: renamed and made unlisted at once): the new card goes out.
            if ($locked->isLive()) {
                $this->publish($locked);
            }

            $harmony->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Every file it has (and its videos'), for deleting or removing it for good.
     */
    public function deleteAll(Harmony $harmony): void
    {
        if ($harmony->media_audio_key !== null) {
            $this->unpublish($harmony);
        }

        $videos = ShareVideo::query()->where('harmony_id', $harmony->id)->whereNotNull('file_key')->pluck('file_key')->all();
        Storage::disk('private')->delete(array_values(array_filter([$harmony->audio_key, $harmony->card_key, $harmony->upload_key, ...$videos])));
        ShareVideo::query()->where('harmony_id', $harmony->id)->delete();

        $harmony->forceFill(['audio_key' => null, 'card_key' => null])->save();
        app(GalleryGuides::class)->removeFor($harmony, null, 'deleted');
        app(OpenParts::class)->close($harmony);
        $this->closeFills($harmony);
    }

    /**
     * New public copies, under new random keys.
     */
    private function publish(Harmony $harmony): void
    {
        $base = 'h/'.Str::lower(Str::random(32));
        $this->copy([(string) $harmony->audio_key => ["{$base}.m4a", 'audio/mp4'], (string) $harmony->card_key => ["{$base}.jpg", 'image/jpeg']]);
        $harmony->forceFill(['media_audio_key' => "{$base}.m4a", 'media_card_key' => "{$base}.jpg"])->save();
    }

    /**
     * Off the bucket first, then forgotten; the CDN's edge is purged by a job that tries again.
     */
    private function unpublish(Harmony $harmony): void
    {
        $keys = array_values(array_filter([$harmony->media_audio_key, $harmony->media_card_key]));
        Storage::disk('media')->delete($keys);
        $harmony->forceFill(['media_audio_key' => null, 'media_card_key' => null])->save();
        PurgeCdn::dispatch($keys);
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $files  private key => [media key, content type]
     */
    private function copy(array $files): void
    {
        $done = [];

        try {
            foreach ($files as $from => [$to, $type]) {
                Storage::disk('media')->put($to, (string) Storage::disk('private')->get($from), ['visibility' => 'public', 'ContentType' => $type, 'CacheControl' => self::CACHE]);
                $done[] = $to;
            }
        } catch (Throwable $e) {
            // Half a copy is never left public with nothing pointing at it.
            Storage::disk('media')->delete($done);

            throw $e;
        }
    }
}
