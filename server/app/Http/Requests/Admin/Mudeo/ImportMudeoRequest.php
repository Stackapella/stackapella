<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mudeo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.mudeo.import: mudeo's users as CSV (id, name, email, created_at, updated_at).
 */
final class ImportMudeoRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ];
    }
}
