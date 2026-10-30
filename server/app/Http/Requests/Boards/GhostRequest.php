<?php

declare(strict_types=1);

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

/**
 * boards.ghost.update: the pitch line of their best take, at most 120 seconds (2,400 frames of
 * little-endian Int16, base64).
 */
final class GhostRequest extends FormRequest
{
    public const MOST_FRAMES = 2400;

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
            'templateVersion' => ['required', 'integer', 'min:1'],
            'scoringVersion' => ['required', 'integer', 'min:1'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'rate' => ['required', 'integer', 'in:20'],
            'startSeconds' => ['required', 'numeric', 'min:0', 'max:3600'],
            'keyOffset' => ['required', 'integer', 'between:-12,12'],
            'pitches' => ['required', 'string', 'max:'.(int) ceil(self::MOST_FRAMES * 2 / 3) * 4, function (string $attribute, mixed $value, \Closure $fail): void {
                $bytes = is_string($value) ? base64_decode($value, true) : false;
                if ($bytes === false || $bytes === '' || strlen($bytes) % 2 !== 0 || strlen($bytes) > self::MOST_FRAMES * 2) {
                    $fail(__('validation.ghost_pitches'));
                }
            }],
        ];
    }

    /**
     * The pitches as stored: the bytes, not their base64.
     */
    public function pitches(): string
    {
        return (string) base64_decode((string) $this->validated('pitches'), true);
    }
}
