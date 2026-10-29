<?php

declare(strict_types=1);

namespace App\Support\Beta;

use App\Models\BetaSinger;
use App\Models\Template;
use App\Models\TemplateGuide;

/**
 * "You're the voice of 3 parts": the default guides sung by this singer, on current versions.
 */
final class VoiceCount
{
    public static function of(BetaSinger $singer): int
    {
        return TemplateGuide::query()
            ->where('is_default', true)
            ->whereIn('template_version_id', Template::query()->official()->whereNotNull('current_version_id')->select('current_version_id'))
            ->whereHas('submission.assignment', fn ($query) => $query->where('beta_singer_id', $singer->id))
            ->count();
    }
}
