<?php

declare(strict_types=1);

namespace App\Http\Requests\SingingTest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * singingTests.create: a finished singing test, numbers only (contract/api.md; docs/singing-test-v1.md).
 */
final class SaveSingingTestRequest extends FormRequest
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
        $cents = ['present', 'nullable', 'numeric', 'between:-1200,1200'];
        $spread = ['present', 'nullable', 'numeric', 'between:0,1200'];
        $ms = ['required', 'integer', 'between:-60000,60000'];

        return [
            'id' => ['required', 'uuid'],
            'testVersion' => ['required', 'integer', 'in:'.implode(',', (array) config('singing_test.test_versions'))],
            'scoringVersion' => ['required', 'integer', 'min:1', 'max:1000'],
            'takenAt' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toIso8601String()],
            'anchor' => ['required', 'numeric', 'between:24,108'],
            'device' => ['required', 'string', 'in:phone,computer'],
            'warmUp' => ['required', 'boolean'],
            'match' => ['present', 'array', 'list', 'max:12'],
            'match.*' => ['array:target,centsOff,octave,landingMs,jitterCents,startAfterPromptMs'],
            'match.*.target' => ['required', 'integer', 'between:0,127'],
            'match.*.centsOff' => $cents,
            'match.*.octave' => ['required', 'integer', 'in:-1,0,1'],
            'match.*.landingMs' => ['present', 'nullable', 'integer', 'between:0,60000'],
            'match.*.jitterCents' => $spread,
            'match.*.startAfterPromptMs' => $ms,
            'back' => ['present', 'array', 'list', 'max:6'],
            'back.*' => ['array:pattern,notes,startOffsetCents,startAfterPromptMs'],
            'back.*.pattern' => ['required', 'array', 'list', 'size:4'],
            'back.*.pattern.*' => ['integer', 'between:0,127'],
            'back.*.notes' => ['required', 'array', 'list', 'size:4'],
            'back.*.notes.*' => ['array:centsOff,jitterCents'],
            'back.*.notes.*.centsOff' => $cents,
            'back.*.notes.*.jitterCents' => $spread,
            'back.*.startOffsetCents' => ['present', 'nullable', 'numeric', 'between:-2400,2400'],
            'back.*.startAfterPromptMs' => $ms,
            'hold' => ['present', 'array', 'list', 'max:2'],
            'hold.*' => ['array:target,spreadCents,driftCents'],
            'hold.*.target' => ['required', 'integer', 'between:0,127'],
            'hold.*.spreadCents' => $spread,
            'hold.*.driftCents' => $cents,
            // The device's own score: kept to compare with the server's, never trusted.
            'score' => ['sometimes', 'nullable', 'array:total,match,back,hold,band'],
            'score.total' => ['nullable', 'integer', 'between:0,100'],
            'score.match' => ['nullable', 'numeric', 'between:0,45'],
            'score.back' => ['nullable', 'numeric', 'between:0,40'],
            'score.hold' => ['nullable', 'numeric', 'between:0,15'],
            'score.band' => ['nullable', 'string', 'in:finding,closer,nearly,inTune,precise'],
        ];
    }
}
