<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.network.update: new thresholds for one network feature.
 */
final class UpdateNetworkRequest extends FormRequest
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
            'thresholds' => ['required', 'array', 'min:1'],
            'thresholds.*' => ['integer', 'between:0,1000000'],
        ];
    }
}
