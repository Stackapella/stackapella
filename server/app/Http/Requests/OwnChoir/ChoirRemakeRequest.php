<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir;

use Illuminate\Foundation\Http\FormRequest;

/** ownChoir.requests.remake: how it's sung (Plus), applied by making the song again. */
final class ChoirRemakeRequest extends FormRequest
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
            'sung' => ['required', 'array'],
            'sung.humanize' => ['required', 'integer', 'between:0,3'],
            'sung.voicing' => ['required', 'in:auto,low,high,written'],
            'sung.breaths' => ['required', 'in:legato,natural'],
            'sung.quality' => ['required', 'in:standard,fine'],
            'sung.ensembleTiming' => ['required', 'in:tight,natural,loose'],
            'sung.pitchSpread' => ['required', 'in:off,subtle,wide'],
        ];
    }
}
