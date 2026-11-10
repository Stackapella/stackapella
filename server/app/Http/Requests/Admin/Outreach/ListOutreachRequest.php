<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Outreach;

use App\Models\OutreachTarget;
use Illuminate\Foundation\Http\FormRequest;

final class ListOutreachRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'in:'.implode(',', [...OutreachTarget::STATUSES, 'follow_up_due'])],
            'group' => ['sometimes', 'string', 'max:40'],
            'angle' => ['sometimes', 'string', 'in:'.implode(',', OutreachTarget::ANGLES)],
            'q' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
