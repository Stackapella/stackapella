<?php

declare(strict_types=1);

namespace App\Http\Requests\Corrections;

use App\Enums\CorrectionKind;
use App\Models\Correction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * corrections.create: anyone, no account. A page of ours, by its path; the honeypot empty.
 */
final class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(CorrectionKind::class)],
            'text' => ['required', 'string', 'min:1', 'max:2000'],
            'source' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'page' => ['required', 'array:type,path,title'],
            'page.type' => ['required', Rule::in(Correction::PAGE_TYPES)],
            'page.path' => ['required', 'string', 'max:255', 'regex:#^/[A-Za-z0-9/_-]*$#'],
            'page.title' => ['required', 'string', 'max:200'],
            'lang' => ['required', Rule::in(['en', 'es', 'de'])],
            'nickname' => ['prohibited'],
        ];
    }
}
