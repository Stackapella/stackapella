<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ownChoir.requests.create: the song, and with a new clip the part sung along to, where beat 0 falls in it, and its
 * numbers (a 16-bit mono WAV recorded in the app, own_choir.clip_min_seconds to clip_max_seconds long), all or none:
 * none makes the song from their kept voice.
 */
final class StartOwnChoirRequest extends FormRequest
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
            // A library song's slug, or (own_choir_songs) one of their own songs' id.
            'templateId' => ['required', 'string', 'regex:/^[a-z0-9-]{1,80}$/'],
            'clipTemplateId' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9-]{1,80}$/'],
            // The tune transposed to the singer's comfortable first note (2026-11-23).
            'clipTranspose' => ['sometimes', 'nullable', 'integer', 'between:-12,12'],
            // The octave the app measured the clip sung in, against the tune as written (2026-11-23).
            'octave' => ['sometimes', 'nullable', 'integer', 'in:-12,0,12'],
            'partKey' => ['required_with:sampleRate', 'string', 'regex:/^[a-z0-9_-]{1,40}$/'],
            'offsetFrames' => ['nullable', 'integer', 'min:-480000', 'max:1440000'],
            'sampleRate' => ['required_with:partKey,frames,bytes,sha256', 'integer', 'min:16000', 'max:48000'],
            'frames' => ['required_with:sampleRate', 'integer', 'min:1', function (string $attribute, mixed $value, Closure $fail): void {
                $rate = $this->input('sampleRate');
                $seconds = is_int($value) && is_int($rate) && $rate > 0 ? $value / $rate : 0;

                if ($seconds < (float) config('own_choir.clip_min_seconds') || $seconds > (float) config('own_choir.clip_max_seconds')) {
                    $fail(__('validation.choir_clip'));
                }
            }],
            'bytes' => ['required_with:sampleRate', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                $frames = $this->input('frames');

                if (! is_int($frames) || $value !== 44 + $frames * 2) {
                    $fail(__('validation.choir_clip'));
                }
            }],
            'sha256' => ['required_with:sampleRate', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            // Turnstile before a person's first song (OwnChoir::checkNeeded): optional, sent only then.
            'turnstileToken' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ];
    }
}
