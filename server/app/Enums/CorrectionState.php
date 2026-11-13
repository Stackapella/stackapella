<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a correction is: new, accepted (to fix), done (fixed), or rejected (with a note why).
 */
enum CorrectionState: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('enums.correction_state.'.$this->value);
    }

    /**
     * The states it may move to: new to accepted or rejected, accepted to done or rejected.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Accepted, self::Rejected],
            self::Accepted => [self::Done, self::Rejected],
            default => [],
        };
    }
}
