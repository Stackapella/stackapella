<?php

declare(strict_types=1);

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * requests.create: the song, who wrote it, and why.
 */
final class CreateSongRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:1', 'max:120'],
            'writer' => ['nullable', 'string', 'max:120'],
            'why' => ['nullable', 'string', 'max:300'],
        ];
    }
}
