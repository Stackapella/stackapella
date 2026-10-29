<?php

declare(strict_types=1);

namespace App\Http\Requests\Sharing;

use App\Models\Harmony;
use App\Rules\NoLinks;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * harmonies.create: the numbers must describe a 16-bit WAV (a 44-byte header), mono or stereo, at
 * 8 to 48 kHz and at most 10 minutes, and each part's outline a byte per 50 ms of it.
 */
final class StartHarmonyRequest extends FormRequest
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
        [$minRate, $maxRate] = (array) config('sharing.sample_rates');
        $maxSeconds = (int) config('sharing.max_seconds');
        $rate = is_numeric($this->input('sampleRate')) ? max(1, (int) $this->input('sampleRate')) : 1;
        $frames = is_numeric($this->input('frames')) ? (int) $this->input('frames') : 0;

        return [
            'changeId' => ['required', 'uuid'],
            'projectId' => ['required', 'uuid'],
            'partId' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:120', new NoLinks],
            'visibility' => ['required', 'string', 'in:'.Harmony::PRIVATE.','.Harmony::UNLISTED],
            'sampleRate' => ['required', 'integer', "between:{$minRate},{$maxRate}"],
            'channels' => ['required', 'integer', 'in:1,2'],
            'frames' => ['required', 'integer', 'min:1', function (string $attribute, mixed $value, Closure $fail) use ($maxSeconds, $rate): void {
                if (is_numeric($value) && (int) $value > $maxSeconds * $rate) {
                    $fail(__('validation.harmony_length', ['minutes' => intdiv($maxSeconds, 60)]));
                }
            }],
            'bytes' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($frames): void {
                if (! is_numeric($value) || ! is_numeric($this->input('channels')) || (int) $value !== $frames * (int) $this->input('channels') * 2 + 44) {
                    $fail(__('validation.harmony_size'));
                }
            }],
            'sha256' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'beatZeroSeconds' => ['nullable', 'numeric', 'between:0,60'],
            'guides' => ['nullable', 'array', 'max:16'],
            // Open parts (N5): a fill of someone's harmony, and the part it sings.
            'parentId' => ['nullable', 'string', 'size:12', 'required_with:fillPart'],
            'fillPart' => ['nullable', 'string', 'max:40', 'required_with:parentId'],
            'guides.*' => ['string', 'max:32'],
            'tracks' => ['required', 'array', 'min:1', 'max:12'],
            'tracks.*' => ['required', 'array:name,color,peaks,kind'],
            // A voice unless it's an instrument (the credits say "played by").
            'tracks.*.kind' => ['sometimes', 'string', 'in:voice,instrument'],
            'tracks.*.name' => ['required', 'string', 'min:1', 'max:60'],
            'tracks.*.color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'tracks.*.peaks' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($frames, $rate): void {
                $bytes = is_string($value) ? base64_decode($value, true) : false;
                $most = (int) ceil($frames / $rate * 20) + 40;

                if ($bytes === false || strlen($bytes) > $most) {
                    $fail(__('validation.track_peaks'));
                }
            }],
        ];
    }
}
