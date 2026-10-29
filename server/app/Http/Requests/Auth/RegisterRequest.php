<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Actions\Beta\AcceptInvite;
use App\Exceptions\Refusal;
use App\Rules\NoLinks;
use App\Rules\OneLine;
use App\Rules\StrongPassword;
use App\Support\Accounts\Age;
use App\Support\Accounts\EarlyAccess;
use App\Support\Flags;
use Illuminate\Foundation\Http\FormRequest;

/**
 * auth.register: an email account. The honeypot and the timing check are read by the controller, so a bot gets the same answer.
 */
final class RegisterRequest extends FormRequest
{
    /**
     * Paused sign-ups are refused before any validation, at once.
     */
    public function authorize(): bool
    {
        $invite = $this->input('invite');
        $early = $this->input('early');

        // Paused, except for someone carrying an open beta invite or the open early-access link.
        if (app(Flags::class)->value('pause_signups') !== 'off' && ! AcceptInvite::opens(is_string($invite) ? $invite : null) && ! EarlyAccess::opens(is_string($early) ? $early : null)) {
            throw new Refusal(423, 'paused');
        }

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', new NoLinks, new OneLine],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', new StrongPassword],
            // When they were born (month and year): the only age answer (Hillel: "drop old 13+ answer").
            'birthMonth' => ['required', 'integer', 'between:1,12'],
            'birthYear' => ['required', 'integer', 'min:'.Age::EARLIEST_YEAR, 'max:'.now()->year],
            'ageConfirmed' => ['prohibited'],
            'ref' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'elapsedMs' => ['nullable', 'integer', 'min:0'],
            'nickname' => ['nullable', 'string', 'max:200'],
            'turnstileToken' => ['nullable', 'string', 'max:4000'],
            'invite' => ['nullable', 'string', 'max:100'],
            'early' => ['nullable', 'string', 'max:100'],
        ];
    }
}
