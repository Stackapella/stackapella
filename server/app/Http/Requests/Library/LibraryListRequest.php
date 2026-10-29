<?php

declare(strict_types=1);

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

/**
 * library.list: words, filters, an order, a page.
 */
final class LibraryListRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:80'],
            'difficulty' => ['sometimes', 'nullable', 'string', 'in:beginner,intermediate,advanced'],
            'parts' => ['sometimes', 'nullable', 'integer', 'in:2,3,4'],
            'genre' => ['sometimes', 'nullable', 'string', 'max:40'],
            'key' => ['sometimes', 'nullable', 'string', 'max:8'],
            'tempo' => ['sometimes', 'nullable', 'string', 'in:slow,medium,fast'],
            'low' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:127'],
            'high' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:127'],
            'creator' => ['sometimes', 'nullable', 'string', 'max:30'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:popular,newest,top'],
            'tab' => ['sometimes', 'nullable', 'string', 'in:featured'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
