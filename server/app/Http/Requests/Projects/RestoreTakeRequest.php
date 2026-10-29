<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

/**
 * takes.restore: where the take plays from its own offset (optional, 0).
 */
final class RestoreTakeRequest extends FormRequest
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
            'nudgeFrames' => ['nullable', 'integer', 'between:-100000000,100000000'],
        ];
    }
}
