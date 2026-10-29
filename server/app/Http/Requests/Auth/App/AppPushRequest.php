<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use App\Models\AppDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * account.devices.push.update: the push token the app's device got from APNs or FCM.
 */
final class AppPushRequest extends FormRequest
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
            'platform' => ['required', 'string', Rule::in(AppDevice::PUSH_SERVICES)],
            'token' => ['required', 'string', 'min:16', 'max:4096', 'regex:/^[A-Za-z0-9_:\-.]+$/'],
            // An APNs token is for one environment: TestFlight and the App Store's is production.
            'environment' => ['required_if:platform,apns', 'nullable', 'string', Rule::in(['sandbox', 'production'])],
        ];
    }
}
