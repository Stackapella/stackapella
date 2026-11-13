<?php

declare(strict_types=1);

namespace App\Http\Requests\Voices;

use Illuminate\Foundation\Http\FormRequest;

/**
 * voices.share: a kept take offered as a part's guide voice, with the volunteers' agreement accepted now.
 */
final class ShareVoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'takeId' => ['required', 'uuid'],
            'templateId' => ['required', 'string', 'max:80'],
            'templateVersion' => ['required', 'integer', 'min:1'],
            'partKey' => ['required', 'string', 'max:40'],
            'credit' => ['required', 'in:name,anonymous'],
            'linksProfile' => ['sometimes', 'boolean'],
            'agreementVersion' => ['required', 'integer'],
            'adult' => ['sometimes', 'boolean'],
        ];
    }
}
