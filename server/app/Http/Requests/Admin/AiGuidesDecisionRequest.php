<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.aiGuides.approve and .reject: the parts by id, exactly those the reviewer saw (a part that arrived after the list
 * loaded is never decided unheard); a rejection's note.
 */
final class AiGuidesDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ids.required' => __('validation.ai_review_choose'), 'note.required' => __('validation.ai_review_note')];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['string', 'max:32'],
            'note' => [$this->routeIs('admin.aiGuides.reject') ? 'required' : 'nullable', 'string', 'min:1', 'max:500'],
        ];
    }
}
