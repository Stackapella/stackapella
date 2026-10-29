<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\ValidCursor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.audit.list's filters.
 */
final class ListAuditRequest extends FormRequest
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
            'action' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'string', 'max:40'],
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
