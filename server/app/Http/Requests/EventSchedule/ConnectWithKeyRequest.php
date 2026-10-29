<?php

declare(strict_types=1);

namespace App\Http\Requests\EventSchedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * eventSchedule.connect.key: a selfhosted install's address and API key.
 */
final class ConnectWithKeyRequest extends FormRequest
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
            'baseUrl' => ['required', 'string', 'url:https,http', 'max:255'],
            'apiKey' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
