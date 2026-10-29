<?php

declare(strict_types=1);

namespace App\Http\Requests\Ideas;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ideas.voices.create: a voice on an idea, described as a take is, and the singer's rights.
 */
final class StartVoiceRequest extends FormRequest
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
        $maxSeconds = (int) config('usage.take_max_seconds');

        return [
            'id' => ['required', 'uuid'],
            'partKey' => ['required', 'string', 'max:40'],
            'sampleRate' => ['required', 'integer', 'between:8000,192000'],
            'frames' => ['required', 'integer', 'min:1', function (string $attribute, mixed $value, Closure $fail) use ($maxSeconds): void {
                if (is_numeric($value) && is_numeric($this->input('sampleRate')) && (int) $value > $maxSeconds * (int) $this->input('sampleRate')) {
                    $fail(__('validation.max.numeric', ['attribute' => $attribute, 'max' => $maxSeconds * (int) $this->input('sampleRate')]));
                }
            }],
            'offsetFrames' => ['required', 'integer', 'between:-100000000,100000000'],
            'bytes' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_numeric($value) || ! is_numeric($this->input('frames')) || (int) $value !== (int) $this->input('frames') * 2 + 44) {
                    $fail(__('validation.take_size'));
                }
            }],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'rightsConfirmed' => ['required', 'accepted'],
        ];
    }
}
