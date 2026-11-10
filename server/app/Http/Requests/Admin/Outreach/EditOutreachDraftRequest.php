<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use Illuminate\Foundation\Http\FormRequest;

final class EditOutreachDraftRequest extends FormRequest
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
            'subject' => ['sometimes', 'string', 'min:1', 'max:200'],
            'body' => ['sometimes', 'string', 'min:1', 'max:10000'],
        ];
    }
}
