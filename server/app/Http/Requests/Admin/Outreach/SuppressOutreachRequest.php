<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use Illuminate\Foundation\Http\FormRequest;

final class SuppressOutreachRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:190'],
            'reason' => ['sometimes', 'string', 'in:no_thanks,unsubscribe,complaint,bounce,manual'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
