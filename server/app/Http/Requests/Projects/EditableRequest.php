<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

/**
 * projects.editable.update: the projects to keep editable.
 */
final class EditableRequest extends FormRequest
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
            'ids' => ['present', 'array', 'max:100'],
            'ids.*' => ['uuid'],
        ];
    }
}
