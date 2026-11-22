<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ownChoir.invites.create: the parts the invite names (optional; by default those that sit high or low for the owner).
 */
final class CreateChoirInviteRequest extends FormRequest
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
            'parts' => ['sometimes', 'array', 'min:1', 'max:8'],
            'parts.*' => ['string', 'max:40', 'distinct'],
        ];
    }
}
