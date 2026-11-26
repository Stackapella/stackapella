<?php

declare(strict_types=1);

namespace App\Support\AiVoices;

use App\Models\AiVoice;
use App\Models\BetaSinger;
use App\Models\TemplateGuide;
use App\Support\Legal\LegalText;

/**
 * The AI voice licence a singer agrees to in the app (docs/ai-voices-build-plan.md, phase 1b;
 * resources/legal/<lang>/ai-voice-licence.md). Offered by an admin (until the lawyer's OK the only way, and with
 * ai_voices off), or to every beta singer once the terms are reviewed and ai_voices is on for them. Its record is the
 * singer's AI voice: the version (consent_version), when (accepted_at), the credit choice, withdrawn and when.
 */
final class AiLicence
{
    public const TEXT = 'ai-voice-licence';

    /** The current terms' version. */
    public static function current(): int
    {
        return once(static fn (): int => (int) app(LegalText::class)->read(self::TEXT)['version']);
    }

    public static function reviewed(): bool
    {
        return once(static fn (): bool => app(LegalText::class)->read(self::TEXT)['reviewed']);
    }

    public static function offered(BetaSinger $singer): bool
    {
        if ($singer->forgotten_at !== null || $singer->revoked_at !== null) {
            return false;
        }

        return $singer->ai_licence_offered_at !== null || (self::reviewed() && AiGuides::on($singer->user));
    }

    /** Their newest AI voice (live or withdrawn), or null. */
    public static function voice(BetaSinger $singer): ?AiVoice
    {
        return AiVoice::query()->where('beta_singer_id', $singer->id)->orderByDesc('id')->first();
    }

    /**
     * May new parts be made in this voice: a voice licensed in the app needs its singer's yes to the current terms (a
     * voice added by command was consented outside the app), and their account's AI features not turned off: consent
     * must be live, so nothing new is made in it for anyone while it's off (docs/ai-optional-plan.md, the coordinator
     * 2026-11-23). What's already made stays.
     */
    public static function licensed(AiVoice $voice): bool
    {
        if ($voice->beta_singer_id === null) {
            return true;
        }

        return $voice->consent_version === (string) self::current() && $voice->loadMissing('betaSinger.user')->betaSinger?->user?->ai_features !== false && $voice->betaSinger?->user?->age_band !== 'u18';
    }

    /**
     * The state the app shows (contract/schemas/beta.aiLicence.json, $defs/state).
     *
     * @return array<string, mixed>
     */
    public static function state(BetaSinger $singer): array
    {
        $voice = self::voice($singer);
        $live = $voice !== null && $voice->isActive() && $voice->accepted_at !== null ? $voice : null;
        $withdrawn = AiVoice::query()->where('beta_singer_id', $singer->id)->whereNotNull('withdrawn_at')->orderByDesc('withdrawn_at')->first();
        $guides = $live === null ? collect() : TemplateGuide::withAi()->with('templateVersion.template')->where('ai_voice_id', $live->id)->orderBy('id')->get();

        return [
            'offered' => self::offered($singer),
            'version' => self::current(),
            'accepted' => $live?->accepted_at === null ? null : [
                'version' => (int) $live->consent_version,
                'acceptedAt' => $live->accepted_at->utc()->toIso8601ZuluString(),
                'credited' => $live->credited,
            ],
            'withdrawnAt' => $withdrawn?->withdrawn_at?->utc()->toIso8601ZuluString(),
            'voice' => $live === null ? null : [
                'id' => $live->sqid(),
                'name' => $live->name,
                'guides' => array_values($guides->map(static fn (TemplateGuide $guide): array => [
                    'id' => $guide->sqid(),
                    'templateId' => (string) $guide->templateVersion->template->slug,
                    'title' => (string) $guide->templateVersion->template->title,
                    'version' => $guide->templateVersion->version,
                    'partKey' => $guide->part_key,
                ])->all()),
            ],
        ];
    }
}
