<?php

declare(strict_types=1);

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

/**
 * templates.rate: one to five stars.
 */
final class RateTemplateRequest extends FormRequest
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
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }
}
