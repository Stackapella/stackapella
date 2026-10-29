<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use Illuminate\Foundation\Http\FormRequest;

/**
 * appPass.claim: which kind of link opened the app, and its code.
 */
final class AppPassRequest extends FormRequest
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
            'kind' => ['required', 'string', 'in:preview,early,invite,beta'],
            'code' => ['required', 'string', 'max:200'],
        ];
    }
}
