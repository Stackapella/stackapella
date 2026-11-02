<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Http\Hosts;

/**
 * To a teacher, once, when Admin approves them: their class codes work now (docs/school-offer-plan.md).
 */
final class ClassCodesReady extends AccountMail
{
    public function __construct(private readonly ?string $teacherName = null) {}

    protected function key(): string
    {
        return 'class_codes_ready';
    }

    protected function url(): string
    {
        return Hosts::appLink('/settings/classes');
    }

    protected function name(): ?string
    {
        return $this->teacherName;
    }
}
