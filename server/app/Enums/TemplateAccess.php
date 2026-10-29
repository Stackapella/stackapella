<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who may start a song: anyone (the free four, and the song of the month while it's free), or
 * Plus accounts.
 */
enum TemplateAccess: string
{
    case Free = 'free';
    case Plus = 'plus';

    public function label(): string
    {
        return __('enums.template_access.'.$this->value);
    }
}
