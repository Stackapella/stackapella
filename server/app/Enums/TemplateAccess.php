<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who may start a library song: anyone, signed out too (Free: the songs the app bundles,
 * config templates.free), or any signed-in account on any plan (Plus, its stored value since
 * before 2026-11-07, when every library song became free with an account; the API sends it as
 * `account`).
 */
enum TemplateAccess: string
{
    case Free = 'free';
    case Plus = 'plus';

    /** As the API sends it: free, or account (contract/api.md). */
    public function api(): string
    {
        return $this === self::Free ? 'free' : 'account';
    }

    public function label(): string
    {
        return __('enums.template_access.'.$this->value);
    }
}
