<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

/**
 * worker.choir.parts.create: one rendered part's numbers, gates and renderer, as worker.ai.uploads.create takes them.
 */
final class CreateChoirPartRequest extends FormRequest
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
            // The token its claim gave: only the claim holding it acts on the job.
            'claim' => ['required', 'string', 'size:32'],
            ...CreateAiUploadRequest::masterRules(fn (string $key): mixed => $this->input($key)),
        ];
    }
}
