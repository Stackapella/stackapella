<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a beta singer is credited on their guides: by the name they choose, or not at all.
 */
enum CreditMode: string
{
    case Name = 'name';
    case Anonymous = 'anonymous';

    public function label(): string
    {
        return __('enums.credit_mode.'.$this->value);
    }
}
