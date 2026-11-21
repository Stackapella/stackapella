<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

/**
 * worker.choir.jobs.finish: null when every part is in, else why it failed.
 */
final class FinishChoirJobRequest extends FormRequest
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
            'failed' => ['present', 'nullable', 'in:clip_unusable,gates_failed,render_failed,reference_gone'],
            // This claim's GPU time so far (the GPU review): optional, 0 or more.
            'gpuSeconds' => ['sometimes', 'numeric', 'min:0', 'max:86400'],
        ];
    }
}
