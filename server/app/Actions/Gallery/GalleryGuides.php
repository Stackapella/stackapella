<?php

declare(strict_types=1);

namespace App\Actions\Gallery;

use App\Actions\Beta\ManageGuides;
use App\Exceptions\Refusal;
use App\Jobs\PublishGalleryGuide;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\Part;
use App\Models\Take;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Gallery\Gallery;

/**
 * Guide voices from public songs (Hillel's call, "public songs' voices can become guides"):
 * Hillel picks a part of a public song (featured, or in the gallery; Gallery::isPublic) from one
 * of our templates, and its take becomes a version of that template part's voice (as a beta
 * singer's does), credited to the singer's profile name. Saying yes to featuring or the gallery
 * agreed to it (and each friend's yes). An unlisted link alone never does. It goes when the song
 * stops being public, is hidden or deleted, or its account goes.
 */
final class GalleryGuides
{
    public function __construct(private readonly Gallery $gallery, private readonly ManageGuides $guides) {}

    public function publish(User $admin, Harmony $harmony, string $partUuid, bool $makeDefault, ?string $ip): void
    {
        if (! $this->gallery->isPublic($harmony)) {
            throw new Refusal(409, 'not_public');
        }

        $template = $harmony->templateVersion?->template;

        if ($template === null || ! $template->isOfficial()) {
            throw new Refusal(409, 'not_official');
        }

        $part = Part::query()->where('uuid', $partUuid)->where('project_id', $harmony->project_id)->first() ?? throw new Refusal(404, 'not_found');
        $take = $part->current_take_id !== null ? Take::query()->whereKey($part->current_take_id)->where('status', Take::READY)->whereNull('binned_at')->first() : null;

        if ($take === null || $part->template_part_key === null || ($harmony->part_id !== null && $harmony->part_id !== $part->id) || ! self::inMix($harmony, $take)) {
            throw new Refusal(409, 'no_take');
        }

        if (! self::creditAdult($harmony, $take)) {
            throw new Refusal(403, 'adult_required');
        }

        AuditLog::record('gallery.guide_published', $harmony, $admin, after: ['part' => $part->template_part_key, 'take' => $take->uuid], ip: $ip);
        PublishGalleryGuide::dispatch($harmony->id, $part->id, $take->id, $makeDefault);
    }

    /**
     * The take was in the mix that was made public, sung by its owner or a friend who agreed. A
     * take recorded since (a re-recording, or someone invited later) never becomes a guide.
     */
    public static function inMix(Harmony $harmony, Take $take): bool
    {
        if ($harmony->take_ids !== null) {
            return in_array($take->id, $harmony->take_ids, true);
        }

        // Shared before the mix's takes were kept: its owner's or a listed friend's, and older.
        $singer = $take->recorded_by ?? $harmony->user_id;

        return ($singer === $harmony->user_id || in_array($singer, $harmony->singer_ids ?? [], true))
            && $take->created_at !== null && $harmony->created_at !== null && $take->created_at->lte($harmony->created_at);
    }

    /**
     * Whoever a guide credits is 18 or over: anything public about a person needs it.
     */
    public static function creditAdult(Harmony $harmony, Take $take): bool
    {
        $credit = User::query()->find($take->recorded_by ?? $harmony->user_id);

        return $credit !== null && Gallery::adult($credit) && ! $credit->isSuspended();
    }

    /**
     * Its song stopped being public (or was hidden, deleted, or its account went): its voices go.
     */
    /**
     * @param  'not_public'|'hidden'|'deleted'  $why  for Hillel's email (ManageGuides::remove)
     */
    public function removeFor(Harmony $harmony, ?User $actor = null, string $why = 'not_public'): void
    {
        foreach (TemplateGuide::query()->where('harmony_id', $harmony->id)->get() as $guide) {
            $this->guides->remove($guide, $actor, null, $why);
        }
    }
}
