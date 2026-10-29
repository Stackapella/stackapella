<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use App\Support\Accounts\HandleRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A handle that's allowed and free (App\Support\Accounts\HandleRules), for this person.
 */
final class AvailableHandle implements ValidationRule
{
    public function __construct(private readonly ?User $for) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = is_string($value) ? app(HandleRules::class)->problem($value, $this->for) : 'invalid';

        if ($problem !== null) {
            $fail(__('validation.handle.'.$problem));
        }
    }
}
