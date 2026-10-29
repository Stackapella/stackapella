<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * harmonies.open: the parts left open (none closes them), and whether they play their guide.
 */
final class OpenPartsRequest extends FormRequest
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
            'parts' => ['present', 'array', 'max:12'],
            'parts.*' => ['string', 'max:40', 'distinct'],
            'guide' => ['sometimes', 'boolean'],
        ];
    }
}
