<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir;

use App\Support\OwnChoir\ChoirSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** ownChoir.requests.sound: choir settings' mix (Plus), saved at once. */
final class ChoirSoundRequest extends FormRequest
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
            'sound' => ['required', 'array'],
            'sound.room' => ['required', 'string', Rule::in(ChoirSettings::ROOMS)],
            'sound.balance' => ['sometimes', 'array', 'max:16'],
            'sound.balance.*.partKey' => ['required', 'string', 'max:40'],
            'sound.balance.*.db' => ['required', 'numeric', 'between:-12,6'],
            'sound.leadBlend' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'sound.recordingLevel' => ['sometimes', 'nullable', 'numeric', 'between:-24,0'],
        ];
    }
}
