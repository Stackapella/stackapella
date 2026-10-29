<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Models\AuditLog;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Services\DigitalOcean\CdnPurge;
use App\Support\Admin\Admins;
use App\Support\Http\Hosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * The real voices per part: one default that learners hear, the rest kept as alternates. Removing
 * one (a singer asked for it) deletes its file from the CDN and falls back to the newest alternate,
 * or the synth.
 */
final class ManageGuides
{
    public function __construct(private readonly CdnPurge $cdn) {}

    public function makeDefault(TemplateGuide $guide, User $actor, ?string $ip = null): TemplateGuide
    {
        DB::transaction(function () use ($guide): void {
            TemplateGuide::query()->where('template_version_id', $guide->template_version_id)->where('part_key', $guide->part_key)->lockForUpdate()->update(['is_default' => false]);
            // A query, not the model: the model may still think it's the default and skip the save.
            TemplateGuide::query()->whereKey($guide->id)->update(['is_default' => true, 'defaulted_at' => now()]);
        });

        $guide->refresh();

        AuditLog::record('beta.guide_default', $guide, $actor, ip: $ip);

        return $guide;
    }

    /**
     * @param  'not_public'|'hidden'|'deleted'|'removed'|'forgotten'  $why
     */
    public function remove(TemplateGuide $guide, ?User $actor, ?string $ip = null, string $why = 'removed'): void
    {
        $next = DB::transaction(function () use ($guide, $actor, $ip): ?TemplateGuide {
            AuditLog::record('beta.guide_removed', $guide, $actor, before: ['part' => $guide->part_key, 'media_key' => $guide->media_key, 'submission' => $guide->beta_submission_id], ip: $ip);
            $guide->delete();
            $next = self::fallback($guide->template_version_id, $guide->part_key);

            if ($guide->is_default) {
                $next?->forceFill(['is_default' => true])->save();
            }

            return $next;
        });

        Storage::disk('media')->delete($guide->media_key);
        $this->cdn->forget([$guide->media_key]);
        $this->tellAdmins($guide, $next !== null, $why);
    }

    /**
     * Hillel's ask: an email at once whenever a part loses a recorded voice, saying what learners
     * hear now and why, so he can choose another. Admins only; nothing about the singer.
     */
    private function tellAdmins(TemplateGuide $guide, bool $another, string $why): void
    {
        $version = TemplateVersion::query()->with('template')->find($guide->template_version_id);
        $to = app(Admins::class)->all();

        if ($version === null || $to->isEmpty()) {
            return;
        }

        $part = collect((array) ($version->data['parts'] ?? []))->firstWhere('key', $guide->part_key);
        $words = ['song' => $version->template->title, 'part' => is_array($part) && is_string($part['name'] ?? null) ? $part['name'] : ucfirst($guide->part_key)];
        Notification::send($to, new OpsAlert(__('ops.voice_lost.subject', $words), __('ops.voice_lost.title'), [
            __('ops.voice_lost.what', $words),
            __($another ? 'ops.voice_lost.now_other' : 'ops.voice_lost.now_synth'),
            __('ops.voice_lost.why.'.$why),
            __('ops.voice_lost.choose', ['url' => Hosts::appUrl().'/admin/guides']),
        ]));
    }

    /**
     * Who leads when a part's default goes (Hillel's decision): the version he made default most
     * recently that's still published, else the newest, else none (the synth plays).
     */
    public static function fallback(int $templateVersionId, string $partKey): ?TemplateGuide
    {
        return TemplateGuide::query()
            ->where('template_version_id', $templateVersionId)
            ->where('part_key', $partKey)
            ->orderByRaw('defaulted_at is null')
            ->orderByDesc('defaulted_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }
}
