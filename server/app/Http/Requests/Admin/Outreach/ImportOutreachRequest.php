<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The research list, as rows (the screen reads the file): each row is checked on its own and a bad
 * one is left out with its reason, so one typo never stops the rest.
 */
final class ImportOutreachRequest extends FormRequest
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
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['array'],
        ];
    }
}
