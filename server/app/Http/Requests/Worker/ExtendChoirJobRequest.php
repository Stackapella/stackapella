<?php

declare(strict_types=1);

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

/**
 * worker.choir.jobs.extend: the claim kept alive, with its GPU time so far.
 */
final class ExtendChoirJobRequest extends FormRequest
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
            'claim' => ['required', 'string', 'size:32'],
            'gpuSeconds' => ['required', 'numeric', 'min:0', 'max:86400'],
        ];
    }
}
