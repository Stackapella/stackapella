<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * worker.choir.references.create: the reference the renderer prepared from a job's clip: a 16-bit mono WAV, and its own
 * metadata (at most own_choir.meta_bytes as JSON).
 */
final class CreateChoirReferenceRequest extends FormRequest
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
        return ['meta.max' => __('validation.choir_meta')];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // The token its claim gave: only the claim holding it acts on the job.
            'claim' => ['required', 'string', 'size:32'],
            'sampleRate' => ['required', 'integer', 'min:16000', 'max:48000'],
            'frames' => ['required', 'integer', 'min:1', 'max:'.(48000 * 60)],
            'bytes' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                $frames = $this->input('frames');

                if (! is_int($frames) || $value !== 44 + $frames * 2) {
                    $fail(__('validation.ai_master_bytes'));
                }
            }],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'meta' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen((string) json_encode($value)) > (int) config('own_choir.meta_bytes')) {
                    $fail(__('validation.choir_meta'));
                }
            }],
        ];
    }
}
