<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use App\Rules\JsonSize;
use Illuminate\Foundation\Http\FormRequest;

/**
 * engagements.create: an offering (a review needs the take) or a pack, and 18 and over.
 */
final class EngagementRequest extends FormRequest
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
            'offeringId' => ['required_without:packId', 'nullable', 'string', 'max:32'],
            'packId' => ['required_without:offeringId', 'nullable', 'uuid'],
            'takeId' => ['nullable', 'uuid'],
            'question' => ['nullable', 'string', 'max:1000'],
            'pitchTrack' => ['nullable', 'array'],
            'scores' => ['nullable', 'array', new JsonSize(16)],
            'adult' => ['nullable', 'boolean'],
            // A lesson: the start time picked from coaches.slots, the singer's zone, and a note.
            'slot' => ['nullable', 'date'],
            'timezone' => ['nullable', 'timezone:all'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
