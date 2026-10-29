<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;

/**
 * groups.eventSchedule.members: the next page's cursor, as ES gave it.
 */
final class ListEsMembersRequest extends FormRequest
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
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }
}
