<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\AppDevice;
use Illuminate\Support\Facades\DB;

/**
 * account.devices.push.update and .delete: the push token of the app's own device. A device holds
 * one token (a new one replaces it), and a token is one device's (one that moved, as after a
 * reinstall, is taken off the device it was on).
 */
final class SetDevicePush
{
    public function set(AppDevice $device, string $service, string $token, ?string $environment): AppDevice
    {
        $hash = hash('sha256', $service.':'.$token);

        return DB::transaction(function () use ($device, $service, $token, $environment, $hash): AppDevice {
            AppDevice::query()->where('push_token_hash', $hash)->whereKeyNot($device->id)->update([...AppDevice::NO_PUSH, 'updated_at' => now()]);
            $device->forceFill([
                'push_service' => $service,
                'push_token' => $token,
                'push_token_hash' => $hash,
                'push_environment' => $service === 'apns' ? ($environment ?? 'production') : null,
                'push_registered_at' => now(),
            ])->save();

            return $device;
        });
    }

    public function clear(AppDevice $device): AppDevice
    {
        $device->forceFill(AppDevice::NO_PUSH)->save();

        return $device;
    }
}
