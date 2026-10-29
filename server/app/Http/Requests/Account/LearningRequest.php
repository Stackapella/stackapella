<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\JsonSize;
use Illuminate\Foundation\Http\FormRequest;

/**
 * account.learning.update: the course's progress object, with its version.
 */
final class LearningRequest extends FormRequest
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
            'changeId' => ['required', 'uuid'],
            'version' => ['required', 'integer', 'min:0'],
            // The epoch the device's progress is from (0, or none from an older app, before any reset).
            'epoch' => ['sometimes', 'integer', 'min:0'],
            'progress' => ['present', 'array', new JsonSize(256)],
        ];
    }
}
