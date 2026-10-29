<?php

declare(strict_types=1);

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.requests.update: its status (and song), a merge, hidden, or approved.
 */
final class UpdateSongRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', 'in:requested,checking,arranging,ready,cant'],
            'templateId' => ['sometimes', 'nullable', 'string', 'max:80'],
            'mergeInto' => ['sometimes', 'nullable', 'string', 'max:32'],
            'hidden' => ['sometimes', 'boolean'],
            'approve' => ['sometimes', 'boolean'],
        ];
    }
}
