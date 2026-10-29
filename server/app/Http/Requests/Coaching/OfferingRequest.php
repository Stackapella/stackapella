<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use App\Rules\NoLinks;
use Illuminate\Foundation\Http\FormRequest;

/**
 * coach.offerings.create and .update: what's sold, at a price in whole cents.
 */
final class OfferingRequest extends FormRequest
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
        $creating = $this->isMethod('post');
        $need = $creating ? 'required' : 'sometimes';
        $lesson = $creating && $this->input('kind') === 'lesson';

        return [
            'kind' => [$creating ? 'required' : 'prohibited', 'string', 'in:review,package,lesson'],
            'title' => [$need, 'string', 'min:1', 'max:80', new NoLinks],
            'description' => [$need, 'string', 'max:1000', new NoLinks],
            'priceCents' => [$need, 'integer', 'between:'.(int) config('coaching.min_price_cents').','.(int) config('coaching.max_price_cents')],
            'currency' => [$need, 'string', 'in:'.implode(',', (array) config('coaching.currencies'))],
            'turnaroundDays' => [$lesson ? 'prohibited' : $need, 'integer', 'between:'.implode(',', (array) config('coaching.turnaround_days'))],
            // A lesson is one of the coach's Event Schedule appointment types.
            'appointmentTypeId' => [$lesson ? 'required' : 'sometimes', 'string', 'max:64'],
            'maxSeconds' => ['nullable', 'integer', 'between:30,600'],
            'reviewsPerMonth' => [$creating && $this->input('kind') === 'package' ? 'required' : 'nullable', 'integer', 'between:1,20'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }
}
