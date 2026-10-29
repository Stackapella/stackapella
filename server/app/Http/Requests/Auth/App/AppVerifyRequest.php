<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.app.verify: the emailed link's token, and the device.
 */
final class AppVerifyRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:200'],
            ...AppDeviceRules::rules(),
        ];
    }
}
