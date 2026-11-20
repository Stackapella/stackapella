<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a guide's voice comes from (docs/ai-voices-build-plan.md): a real singer's take, or an AI voice standing in
 * until a real singer records the part.
 */
enum GuideSource: string
{
    case Recorded = 'recorded';
    case Ai = 'ai';

    public function label(): string
    {
        return __('enums.guide_source.'.$this->value);
    }
}
