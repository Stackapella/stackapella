<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mudeo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.mudeo.update: how many of the mudeo email may go a day.
 */
final class UpdateMudeoRequest extends FormRequest
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
            'dailyCap' => ['required', 'integer', 'between:1,2000'],
        ];
    }
}
