<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mudeo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.mudeo.batches.create: the addresses just sent in one Bcc.
 */
final class SendMudeoBatchRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['string', 'max:32'],
        ];
    }
}
