<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Moderation\TextFilter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * No links in what people type, at launch (docs/trust-and-safety-plan.md, "Rules for anything
 * people type").
 */
final class NoLinks implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && TextFilter::hasLink($value)) {
            $fail(__('validation.no_links'));
        }
    }
}
