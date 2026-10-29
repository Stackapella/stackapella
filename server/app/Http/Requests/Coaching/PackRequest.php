<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use App\Rules\JsonSize;
use App\Rules\NoLinks;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * coach.packs.save: a pack of the warm-ups' exercises, free or at a price.
 */
final class PackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:80', new NoLinks],
            'description' => ['required', 'string', 'max:1000', new NoLinks],
            // Free, or at least the coaching minimum.
            'priceCents' => ['required', 'integer', 'min:0', 'max:'.(int) config('coaching.max_price_cents'), function (string $attribute, mixed $value, Closure $fail): void {
                if (is_int($value) && $value > 0 && $value < (int) config('coaching.min_price_cents')) {
                    $fail(__('validation.min.numeric', ['attribute' => $attribute, 'min' => (int) config('coaching.min_price_cents')]));
                }
            }],
            'currency' => ['required', 'string', 'in:'.implode(',', (array) config('coaching.currencies'))],
            'exercises' => ['required', 'array', 'min:1', 'max:30', new JsonSize(256)],
            'exercises.*' => ['array'],
            'exercises.*.id' => ['required', 'string', 'max:80'],
            'exercises.*.title' => ['required', 'string', 'max:120'],
        ];
    }
}
