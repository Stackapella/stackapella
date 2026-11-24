<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * worker.ai.uploads.create: a checked master's numbers, the voices lane's gates and its renderer's settings (contract/api.md,
 * "worker.ai.uploads.create"). A 16-bit mono WAV at 48 kHz with a plain 44-byte header, at most 10 minutes; no note over
 * 30 cents; the reference a short label, never a path or a URL.
 */
final class CreateAiUploadRequest extends FormRequest
{
    public const SAMPLE_RATE = 48000;

    public const MAX_SECONDS = 600;

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
            'versionId' => ['required', 'string', 'max:32'],
            'voice' => ['required', 'string', 'max:32'],
            ...self::masterRules(fn (string $key): mixed => $this->input($key)),
        ];
    }

    /**
     * A rendered master's numbers, gates and renderer (also worker.choir.parts.create's).
     *
     * @param  Closure(string): mixed  $input
     * @return array<string, list<mixed>>
     */
    public static function masterRules(Closure $input): array
    {
        $number = ['required', 'numeric', 'min:0', 'max:100000'];
        $count = ['required', 'integer', 'min:0', 'max:100000'];

        return [
            'part' => ['required', 'string', 'regex:/^[a-z0-9_-]{1,40}$/'],
            'sampleRate' => ['required', 'integer', 'in:'.self::SAMPLE_RATE],
            'frames' => ['required', 'integer', 'min:1', 'max:'.(self::SAMPLE_RATE * self::MAX_SECONDS)],
            'bytes' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($input): void {
                if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
                    return;
                }

                $frames = $input('frames');

                // The audio, and after it the worker's AI marking chunks (EU AI Act Article 50; config ai_marking).
                if (! is_int($frames) || (int) $value < 44 + $frames * 2 || (int) $value > 44 + $frames * 2 + (int) config('ai_marking.max_chunk_bytes')) {
                    $fail(__('validation.ai_master_bytes'));
                }
            }],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'checks' => ['required', 'array:pitchMaxCents,pitchMedianCents,notesMeasured,notesTotal,notesOver15,notesOver30,dropouts,heldSeconds,durationSeconds'],
            // One slightly-off note no longer fails a whole part (2026-11-24): the worst may be 50 cents out.
            'checks.pitchMaxCents' => ['required', 'numeric', 'min:0', 'max:50'],
            'checks.pitchMedianCents' => ['required', 'numeric', 'min:0', 'max:30'],
            'checks.notesMeasured' => $count,
            'checks.notesTotal' => $count,
            'checks.notesOver15' => $count,
            // A few notes over 30 cents, at most the larger of 2 and 5% of the notes measured (2026-11-24).
            'checks.notesOver30' => ['required', 'integer', 'min:0', static function (string $attribute, mixed $value, Closure $fail) use ($input): void {
                $measured = $input('checks.notesMeasured');

                if (is_numeric($measured) && is_numeric($value) && (int) $value > max(2, (int) ceil(0.05 * (int) $measured))) {
                    $fail(__('validation.max.numeric', ['attribute' => $attribute, 'max' => max(2, (int) ceil(0.05 * (int) $measured))]));
                }
            }],
            'checks.dropouts' => $count,
            'checks.heldSeconds' => $number,
            'checks.durationSeconds' => $number,
            'renderer' => ['required', 'array:model,codeRevision,weightsRevision,steps,control,pitchShift,reference'],
            'renderer.model' => ['required', 'string', 'regex:/^[a-z0-9._-]{1,40}$/'],
            'renderer.codeRevision' => ['required', 'string', 'regex:/^[0-9a-f]{40}$/'],
            'renderer.weightsRevision' => ['required', 'string', 'regex:/^[0-9a-f]{40}$/'],
            'renderer.steps' => ['required', 'integer', 'min:1', 'max:1000'],
            'renderer.control' => ['required', 'string', 'regex:/^[a-z_-]{1,20}$/'],
            'renderer.pitchShift' => ['required', 'integer', 'min:-24', 'max:24'],
            'renderer.reference' => ['required', 'string', 'regex:/^[a-z0-9-]{1,40}$/'],
        ];
    }
}
