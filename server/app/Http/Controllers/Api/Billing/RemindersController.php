<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PushDeviceRequest;
use App\Http\Requests\Billing\RemindersRequest;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * reminders.*: the practice reminder (web push, opt-in; docs/operations-plan.md) and the devices
 * that get it. A device is changed or removed by its own token, as the service worker can send
 * neither a session nor CSRF.
 */
final class RemindersController extends Controller
{
    public function show(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $endpoint = $request->query('endpoint');

        return response()->json(['data' => $this->fields($user, is_string($endpoint) ? $endpoint : null)]);
    }

    public function update(RemindersRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var list<int> $days */
        $days = array_values(array_unique(array_map('intval', (array) $request->validated('days'))));
        sort($days);

        $user->forceFill([
            'reminder_enabled' => $request->boolean('enabled'),
            'reminder_days' => $days,
            'reminder_time' => $request->validated('time'),
            'reminder_timezone' => $request->validated('timezone'),
        ])->save();

        return response()->json(['data' => $this->fields($user, null)]);
    }

    public function addDevice(PushDeviceRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $endpoint = (string) $request->validated('endpoint');
        $token = 'dvc_'.Str::random(40);

        $device = PushDevice::query()->where('endpoint_hash', hash('sha256', $endpoint))->first() ?? new PushDevice;
        $device->fill(['endpoint' => $endpoint, 'p256dh' => (string) $request->validated('keys.p256dh'), 'auth' => (string) $request->validated('keys.auth')]);
        $device->forceFill(['endpoint_hash' => hash('sha256', $endpoint), 'user_id' => $user->id, 'token_hash' => hash('sha256', $token)])->save();

        // A handful of devices each: the oldest goes past the limit.
        $keep = PushDevice::query()->where('user_id', $user->id)->latest('id')->limit((int) config('services.vapid.devices_per_person'))->pluck('id');
        PushDevice::query()->where('user_id', $user->id)->whereNotIn('id', $keep)->delete();

        return response()->json(['data' => ['id' => $device->sqid(), 'token' => $token]], 201);
    }

    public function updateDevice(PushDeviceRequest $request, string $device): Response
    {
        $found = $this->byToken($request, $device);

        if ($found === null) {
            throw new Refusal(404, 'not_found');
        }

        $endpoint = (string) $request->validated('endpoint');
        // A new endpoint someone else's device had: theirs goes (the push service reassigned it).
        PushDevice::query()->where('endpoint_hash', hash('sha256', $endpoint))->whereKeyNot($found->id)->delete();
        $found->fill(['endpoint' => $endpoint, 'p256dh' => (string) $request->validated('keys.p256dh'), 'auth' => (string) $request->validated('keys.auth')]);
        $found->forceFill(['endpoint_hash' => hash('sha256', $endpoint)])->save();

        return response()->noContent();
    }

    public function removeDevice(Request $request, string $device): Response
    {
        $this->byToken($request, $device)?->delete();

        return response()->noContent();
    }

    private function byToken(Request $request, string $id): ?PushDevice
    {
        $token = $request->header('X-Device-Token');
        $device = PushDevice::query()->whereSqid($id)->first();

        return $device !== null && is_string($token) && hash_equals($device->token_hash, hash('sha256', $token)) ? $device : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(User $user, ?string $endpoint): array
    {
        $key = (string) config('services.vapid.public_key');

        return [
            'enabled' => $user->reminder_enabled,
            'days' => $user->reminder_days ?? [],
            'time' => $user->reminder_time,
            'timezone' => $user->reminder_timezone,
            'vapidPublicKey' => $key !== '' ? $key : null,
            'devices' => PushDevice::query()->where('user_id', $user->id)->count(),
            'thisDevice' => $endpoint !== null ? PushDevice::query()->where('user_id', $user->id)->where('endpoint_hash', hash('sha256', $endpoint))->exists() : null,
        ];
    }
}
