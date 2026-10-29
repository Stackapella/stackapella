<?php

declare(strict_types=1);

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.library.feature: on or off the Featured shelf.
 */
final class FeatureTemplateRequest extends FormRequest
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
            'featured' => ['required', 'boolean'],
        ];
    }
}
