<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Pagination\Cursor;
use Throwable;

/**
 * A list's ?cursor= must be one we handed out (meta.nextCursor): decodable, and pointing at an id.
 * A crafted one would otherwise end in a 500 inside the paginator.
 */
final class ValidCursor implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $cursor = is_string($value) ? Cursor::fromEncoded($value) : null;
            $valid = $cursor !== null && is_numeric($cursor->parameter('id'));
        } catch (Throwable) {
            // Any cursor that can't be read is simply not one of ours.
            $valid = false;
        }

        if (! $valid) {
            $fail(__('validation.cursor'));
        }
    }
}
