<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Trust\WordLists;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * At least 10 characters, and not one of the 10,000 most common (docs/trust-and-safety-plan.md,
 * "Accounts and admin, hardened"). No outside call.
 */
final class StrongPassword implements ValidationRule
{
    public const MIN = 10;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < self::MIN) {
            $fail(__('validation.min.string', ['attribute' => $attribute, 'min' => self::MIN]));
        } elseif (mb_strlen($value) > 200) {
            $fail(__('validation.max.string', ['attribute' => $attribute, 'max' => 200]));
        } elseif (WordLists::isCommonPassword($value)) {
            $fail(__('validation.common_password'));
        }
    }
}
