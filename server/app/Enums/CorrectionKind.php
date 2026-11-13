<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a reader's correction is about (docs/corrections-plan.md): a fact in a page, a song's words, its notes, or
 * anything else.
 */
enum CorrectionKind: string
{
    case Fact = 'fact';
    case Words = 'words';
    case Notes = 'notes';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.correction_kind.'.$this->value);
    }
}
