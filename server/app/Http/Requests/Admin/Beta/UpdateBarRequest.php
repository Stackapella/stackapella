<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.beta.bar.update: the pitch and timing a take must reach, each 50 to 100.
 */
final class UpdateBarRequest extends FormRequest
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
            'pitchPercent' => ['sometimes', 'integer', 'between:50,100'],
            'timingPercent' => ['sometimes', 'integer', 'between:50,100'],
        ];
    }
}
