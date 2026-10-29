<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use Illuminate\Foundation\Http\FormRequest;

/**
 * coaches.slots: from when, for how many days, and in whose time zone.
 */
final class ListSlotsRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'between:1,31'],
            'timezone' => ['nullable', 'timezone:all'],
        ];
    }
}
