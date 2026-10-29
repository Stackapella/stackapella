<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Beta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accept, ask for another take, or reject: a note is required for another take.
 */
final class DecideSubmissionRequest extends FormRequest
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
            'makeDefault' => ['sometimes', 'boolean'],
            'note' => [$this->routeIs('admin.beta.submissions.retake') ? 'required' : 'nullable', 'string', 'max:1000'],
            'beat' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }
}
