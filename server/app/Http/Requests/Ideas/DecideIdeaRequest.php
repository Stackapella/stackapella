<?php

declare(strict_types=1);

namespace App\Http\Requests\Ideas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.ideas.decide: approve, hide (with the reasons) or show, an idea or one voice.
 */
final class DecideIdeaRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:approve,hide,show'],
            'note' => ['required_if:decision,hide', 'nullable', 'string', 'max:2000'],
            'voiceId' => ['nullable', 'uuid'],
        ];
    }
}
