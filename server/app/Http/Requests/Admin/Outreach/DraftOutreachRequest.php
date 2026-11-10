<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use Illuminate\Foundation\Http\FormRequest;

final class DraftOutreachRequest extends FormRequest
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
            'kind' => ['sometimes', 'string', 'in:first,follow_up'],
        ];
    }
}
