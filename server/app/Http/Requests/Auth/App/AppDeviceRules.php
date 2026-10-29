<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth\App;

use App\Models\AppDevice;

/**
 * The device signing in, as every app sign-in sends it (contract/api.md, "The apps: device tokens").
 */
final class AppDeviceRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'device' => ['required', 'array:name,platform,model,appVersion,replace'],
            'device.name' => ['required', 'string', 'max:80'],
            'device.platform' => ['required', 'string', 'in:'.implode(',', AppDevice::PLATFORMS)],
            'device.model' => ['nullable', 'string', 'max:80'],
            'device.appVersion' => ['nullable', 'string', 'max:40'],
            'device.replace' => ['nullable', 'string', 'max:32'],
        ];
    }
}
