<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.reports.list: what's waiting (open), or what's been dealt with (closed).
 */
final class ListReportsRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:open,closed'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }
}
