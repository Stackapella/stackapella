<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * explore.list: a tab, a song, some words.
 */
final class ExploreRequest extends FormRequest
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
            'tab' => ['sometimes', 'string', 'in:new,featured,following,open'],
            'song' => ['sometimes', 'nullable', 'string', 'max:80'],
            'q' => ['sometimes', 'nullable', 'string', 'max:80'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
