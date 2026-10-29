<?php

declare(strict_types=1);

namespace App\Http\Requests\Collaboration;

use App\Rules\NoLinks;
use Illuminate\Foundation\Http\FormRequest;

/**
 * invites.create: which part the invite is for, if any.
 */
final class CreateInviteRequest extends FormRequest
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
            'partKey' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'partName' => ['nullable', 'string', 'max:60', new NoLinks],
        ];
    }
}
