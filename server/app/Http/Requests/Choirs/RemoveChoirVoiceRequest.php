<?php

declare(strict_types=1);

namespace App\Http\Requests\Choirs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.choirs.voices.remove: the statement of reasons.
 */
final class RemoveChoirVoiceRequest extends FormRequest
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
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
