<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.harmonies.feature: in the gallery's Featured, or not.
 */
final class FeatureRequest extends FormRequest
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
