<?php

declare(strict_types=1);

namespace App\Http\Requests\Counts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * counts.create: a few event kinds, each with how many times, whether the person is signed in, and
 * the device's experiment arm for the funnel's kinds.
 */
final class RecordCountsRequest extends FormRequest
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
            'events' => ['required', 'array', 'max:'.(int) config('counts.max_events')],
            'events.*.kind' => ['required', 'string', 'max:32'],
            'events.*.n' => ['nullable', 'integer', 'min:1'],
            'signedIn' => ['nullable', 'boolean'],
            // The funnel's experiment arm ("onb1-b"); one the server doesn't know is counted without it.
            'variant' => ['nullable', 'string', 'max:40'],
            // Where the device came from: its stored ?ref= tag, grouped by the server (never kept as sent).
            'source' => ['nullable', 'string', 'max:40'],
        ];
    }
}
