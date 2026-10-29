<?php

declare(strict_types=1);

namespace App\Http\Requests\Sharing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * videos.create: the section, the look and the words. The section's length and place are checked
 * against the harmony by the action.
 */
final class StartVideoRequest extends FormRequest
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
            'startSeconds' => ['required', 'numeric', 'min:0', 'max:600'],
            'endSeconds' => ['required', 'numeric', 'gt:startSeconds', 'max:600'],
            'look' => ['nullable', 'string', 'in:dark,light'],
            'words' => ['nullable', 'boolean'],
        ];
    }
}
