<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

/**
 * worker.ai.needs: one song at a time, by its slug. The worker's token was checked by AuthenticateAiWorker.
 */
final class AiNeedsRequest extends FormRequest
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
            'template' => ['required', 'string', 'regex:/^[a-z0-9-]{1,80}$/'],
        ];
    }
}
