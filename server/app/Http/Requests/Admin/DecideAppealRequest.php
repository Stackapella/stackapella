<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.appeals.decide: kept or reversed, and why.
 */
final class DecideAppealRequest extends FormRequest
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
            'decision' => ['required', 'in:kept,reversed'],
            'note' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }
}
