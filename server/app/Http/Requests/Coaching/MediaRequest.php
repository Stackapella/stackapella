<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * reviews.media.create: a 16-bit mono WAV (a 44-byte header), as a take.
 */
final class MediaRequest extends FormRequest
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
            'id' => ['required', 'uuid'],
            'kind' => ['required', 'string', 'in:voice_note,demo'],
            'sampleRate' => ['required', 'integer', 'between:8000,192000'],
            'frames' => ['required', 'integer', 'min:1'],
            'bytes' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_numeric($value) || ! is_numeric($this->input('frames')) || (int) $value !== (int) $this->input('frames') * 2 + 44) {
                    $fail(__('validation.take_size'));
                }
            }],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
        ];
    }
}
