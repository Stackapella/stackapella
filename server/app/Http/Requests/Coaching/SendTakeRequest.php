<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use App\Rules\JsonSize;
use Illuminate\Foundation\Http\FormRequest;

/**
 * reviews.create: another take, its pitch track and scores, and a question.
 */
final class SendTakeRequest extends FormRequest
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
            'takeId' => ['required', 'uuid'],
            'question' => ['nullable', 'string', 'max:1000'],
            'pitchTrack' => ['nullable', 'array'],
            'scores' => ['nullable', 'array', new JsonSize(16)],
        ];
    }
}
