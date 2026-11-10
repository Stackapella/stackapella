<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use Illuminate\Foundation\Http\FormRequest;

final class LogOutreachReplyRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:20000'],
            'from' => ['sometimes', 'nullable', 'email', 'max:190'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:200'],
            'receivedAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
