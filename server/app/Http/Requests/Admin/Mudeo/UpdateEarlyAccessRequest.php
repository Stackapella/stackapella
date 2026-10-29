<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mudeo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.earlyAccess.update: the link's cap, or a pause.
 */
final class UpdateEarlyAccessRequest extends FormRequest
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
            'cap' => ['sometimes', 'integer', 'between:1,100000'],
            'paused' => ['sometimes', 'boolean'],
        ];
    }
}
