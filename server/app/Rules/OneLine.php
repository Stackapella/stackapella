<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A name on one line: no line breaks or other control characters (docs/security-review.md). A
 * name shows up in other people's email subjects, where a line break could start a new header.
 */
final class OneLine implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/[\p{Cc}\x{2028}\x{2029}]/u', $value) === 1) {
            $fail(__('validation.one_line'));
        }
    }
}
