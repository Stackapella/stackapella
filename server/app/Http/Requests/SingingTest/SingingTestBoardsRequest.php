<?php

declare(strict_types=1);

namespace App\Http\Requests\SingingTest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * singingTests.boards.show: which board, whose, and which month (this UTC month when left out).
 */
final class SingingTestBoardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'board' => ['required', 'string', 'in:improved,best'],
            'scope' => ['sometimes', 'string', 'in:global,friends'],
            'month' => ['sometimes', 'nullable', 'date_format:Y-m'],
        ];
    }
}
