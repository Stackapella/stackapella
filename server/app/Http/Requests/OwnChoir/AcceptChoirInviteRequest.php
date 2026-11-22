<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ownChoir.invites.accept: the friend's yes (show their name or not; an own song's words hash), and their saved voice
 * or a new clip's fields, as requests.create takes them.
 */
final class AcceptChoirInviteRequest extends FormRequest
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
            // The exact parts they saw (invites.show's yourParts) and say yes to.
            'parts' => ['present', 'array', 'max:8'],
            'parts.*' => ['string', 'max:40'],
            'showName' => ['required', 'boolean'],
            'wordsSha' => ['sometimes', 'nullable', 'string', 'size:64'],
            'useSavedVoice' => ['sometimes', 'boolean'],
            'partKey' => ['required_with:sampleRate', 'nullable', 'string', 'max:40'],
            // The octave the app measured the tune sung in, on the take still on the device (2026-11-23).
            'octave' => ['sometimes', 'nullable', 'integer', 'in:-12,0,12'],
            'offsetFrames' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1440000'],
            'sampleRate' => ['sometimes', 'nullable', 'integer', 'in:16000,22050,24000,32000,44100,48000'],
            'frames' => ['required_with:sampleRate', 'nullable', 'integer', 'min:1'],
            'bytes' => ['required_with:sampleRate', 'nullable', 'integer', 'min:45', 'max:'.(31 * 48000 * 2 + 44)],
            'sha256' => ['required_with:sampleRate', 'nullable', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
        ];
    }
}
