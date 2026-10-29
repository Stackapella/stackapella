<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.people.trust.update: a level from 0 to 3, or null to go back to the earned one, and a note.
 */
final class TrustRequest extends FormRequest
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
            'level' => ['present', 'nullable', 'integer', 'between:0,3'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }
}
