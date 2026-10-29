<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A push subscription (PushSubscription.toJSON()): an https endpoint and its keys.
 */
final class PushDeviceRequest extends FormRequest
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
            'endpoint' => ['required', 'string', 'max:1000', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                if (! self::pushService((string) $value)) {
                    $fail(__('validation.url', ['attribute' => $attribute]));
                }
            }],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['endpoint.url' => __('validation.push_endpoint')];
    }

    /**
     * Whether an endpoint is on a browser's push service (config/services.php, vapid.push_hosts).
     */
    public static function pushService(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        foreach ((array) config('services.vapid.push_hosts') as $allowed) {
            $allowed = (string) $allowed;

            if ($host === $allowed || (str_starts_with($allowed, '.') && str_ends_with($host, $allowed))) {
                return true;
            }
        }

        return false;
    }
}
