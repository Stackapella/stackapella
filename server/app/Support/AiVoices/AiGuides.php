<?php

declare(strict_types=1);

namespace App\Support\AiVoices;

use App\Actions\Counts\RecordCounts;
use App\Enums\GuideSource;
use App\Models\AuditLog;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Services\DigitalOcean\CdnPurge;
use App\Support\Flags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The rules for AI guide voices (docs/ai-voices-build-plan.md, backend item 3), in one place:
 * - who hears them: the ai_voices switch, read for the person asking (on for one account first), and only a voice whose
 *   consent is recorded (shownWith);
 * - a real voice replaces one: publishing or carrying a recorded guide to a part retires that part's AI guide at once
 *   (retireForPart: deleted, then purged from the CDN once the change is committed);
 * - a withdrawn voice takes every guide it sang with it (App\Actions\AiVoices\WithdrawAiVoice uses retire()).
 * Never carried to a new version: CarryVoicesOver sees recorded guides only (TemplateGuide's RecordedGuides scope).
 */
final class AiGuides
{
    public const FLAG = 'ai_voices';

    /** Whether AI guides reach this person (signed out: whether the switch is on for everyone). */
    public static function on(?User $user): bool
    {
        $flags = app(Flags::class);

        return $flags->exists(self::FLAG) && $flags->onFor(self::FLAG, $user);
    }

    /**
     * Guides as the app and the site may hear them: recorded ones, and with `$ai` the AI ones of a consented, active voice.
     *
     * @return Builder<TemplateGuide>
     */
    public static function shownWith(bool $ai): Builder
    {
        if (! $ai) {
            return TemplateGuide::query();
        }

        return TemplateGuide::withAi()->where(fn (Builder $query) => $query
            ->where('source', GuideSource::Recorded->value)
            ->orWhere(fn (Builder $ai) => $ai->where('source', GuideSource::Ai->value)->whereHas('aiVoice', fn (Builder $voice) => $voice->whereNull('withdrawn_at')->whereNotNull('consent_date'))));
    }

    /**
     * A recorded voice now sings this part of this version: its AI guide goes (inside the caller's transaction; the
     * files go once it commits). Counted as `ai_replaced`.
     *
     * @return int how many were retired
     */
    public static function retireForPart(int $templateVersionId, string $partKey): int
    {
        $guides = TemplateGuide::withAi()->where('source', GuideSource::Ai->value)
            ->where('template_version_id', $templateVersionId)->where('part_key', $partKey)->lockForUpdate()->get();

        self::retire($guides, null, 'replaced');

        return $guides->count();
    }

    /**
     * Retire these AI guides: their rows deleted now, their files deleted and purged from the CDN once the change is
     * committed, each written to the audit log.
     *
     * @param  iterable<TemplateGuide>  $guides
     * @param  'replaced'|'withdrawn'|'rerendered'|'removed'  $why  a recorded voice took the part (counted as ai_replaced), the voice
     *                                                              was withdrawn, or a new AI render replaced it, or Hillel retired it in Admin
     */
    public static function retire(iterable $guides, ?User $actor, string $why): void
    {
        $keys = [];

        foreach ($guides as $guide) {
            if (! $guide->isAi()) {
                continue;
            }

            AuditLog::record('ai.guide_retired', $guide, $actor, before: ['part' => $guide->part_key, 'version' => $guide->template_version_id, 'voice' => $guide->ai_voice_id, 'media_key' => $guide->media_key, 'why' => $why]);
            TemplateGuide::withAi()->whereKey($guide->id)->delete();
            $keys[] = $guide->media_key;
        }

        if ($keys === []) {
            return;
        }

        DB::afterCommit(static function () use ($keys, $why): void {
            Storage::disk('media')->delete($keys);
            app(CdnPurge::class)->forget($keys);

            if ($why === 'replaced') {
                foreach ($keys as $ignored) {
                    app(RecordCounts::class)->server('ai_replaced', true, null);
                }
            }
        });
    }
}
