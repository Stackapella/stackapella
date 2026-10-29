<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SecurityEventType;
use App\Rules\ValidCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.securityEvents.list's filters.
 */
final class ListSecurityEventsRequest extends FormRequest
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
            'type' => ['nullable', Rule::enum(SecurityEventType::class)],
            'user' => ['nullable', 'string', 'max:32'],
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
