<?php

declare(strict_types=1);

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.library.decide: approve, hide (with the reasons), or show again.
 */
final class DecideTemplateRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:approve,hide,show'],
            'note' => ['required_if:decision,hide', 'nullable', 'string', 'max:2000'],
        ];
    }
}
