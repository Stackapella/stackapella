<?php

declare(strict_types=1);

namespace App\Http\Requests\Choirs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * choirs.join: the take, its part, the app's pitch score, how to be credited, and the rights.
 */
final class JoinChoirRequest extends FormRequest
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
            'partKey' => ['required', 'string', 'max:40'],
            'score' => ['required', 'integer', 'between:0,100'],
            'credit' => ['required', 'string', 'in:handle,anonymous'],
            'rightsConfirmed' => ['required', 'accepted'],
        ];
    }
}
