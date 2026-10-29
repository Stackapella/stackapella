<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use App\Rules\NoLinks;
use Illuminate\Foundation\Http\FormRequest;

/**
 * coach.profile.update: the public profile's fields.
 */
final class ProfileRequest extends FormRequest
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
            'bio' => ['sometimes', 'string', 'max:1000', new NoLinks],
            'voiceTypes' => ['sometimes', 'array', 'min:1', 'max:6'],
            'voiceTypes.*' => ['string', 'in:soprano,mezzo,alto,tenor,baritone,bass'],
            'styles' => ['sometimes', 'array', 'max:10'],
            'styles.*' => ['string', 'max:40'],
            'languages' => ['sometimes', 'array', 'min:1', 'max:10'],
            'languages.*' => ['string', 'regex:/^[a-z]{2,3}$/'],
            'timezone' => ['sometimes', 'timezone:all'],
            'cancellationHours' => ['sometimes', 'integer', 'between:0,168'],
        ];
    }
}
