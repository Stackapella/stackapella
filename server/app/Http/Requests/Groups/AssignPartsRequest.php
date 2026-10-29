<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;

/**
 * groups.assignments.update: the whole list of who sings which part of one song.
 */
final class AssignPartsRequest extends FormRequest
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
            'assignments' => ['present', 'array', 'max:200'],
            'assignments.*' => ['array:memberId,partKey'],
            'assignments.*.memberId' => ['required', 'string', 'max:32'],
            'assignments.*.partKey' => ['required', 'string', 'max:40'],
        ];
    }
}
