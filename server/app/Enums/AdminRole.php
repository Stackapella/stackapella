<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * An editor works the queues; money, bans, deletes, settings and switches are the admin's
 * (docs/operations-plan.md, "A moderator role").
 */
enum AdminRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';

    public function label(): string
    {
        return __('enums.admin_role.'.$this->value);
    }
}
