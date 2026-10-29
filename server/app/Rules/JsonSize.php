<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An object the app keeps as it is (a project's or part's settings, the course's progress), at
 * most so many KB as JSON.
 */
final class JsonSize implements ValidationRule
{
    public function __construct(private readonly int $kb) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen((string) json_encode($value)) > $this->kb * 1024) {
            $fail(__('validation.settings_size', ['kb' => $this->kb]));
        }
    }
}
