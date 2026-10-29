<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Beta\AcceptInvite;
use App\Actions\Users\SetDevicePush;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateAppDevice;
use App\Http\Requests\Auth\App\AppPassRequest;
use App\Http\Requests\Auth\App\AppPushRequest;
use App\Models\AppDevice;
use App\Models\EarlyAccessLink;
use App\Models\User;
use App\Support\Accounts\AppTokens;
use App\Support\Accounts\BetaPass;
use App\Support\Accounts\EarlyAccess;
use App\Support\Ids;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * account.devices.list and .delete (Settings, "Signed in on"), the device's push token
 * (account.devices.push.*), and appPass.claim: the beta pass for the apps, which keep no cookie.
 */
final class AppDevicesController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $current = $request->attributes->get(AuthenticateAppDevice::DEVICE);
        $devices = AppDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->where('refresh_expires_at', '>', now())
            ->orderByDesc('last_used_at')->orderByDesc('id')->limit(100)->get();

        return response()->json([
            'data' => array_values($devices->map(static fn (AppDevice $d): array => AppTokens::view($d, $current instanceof AppDevice ? $current : null))->all()),
            'meta' => ['nextCursor' => null],
        ]);
    }

    public function destroy(string $device, #[CurrentUser] User $user): Response
    {
        $found = AppDevice::query()->whereKey(Ids::decode($device) ?? 0)->where('user_id', $user->id)->whereNull('revoked_at')->first() ?? throw new Refusal(404, 'not_found');
        $found->forceFill(['revoked_at' => now()])->save();

        return response()->noContent();
    }

    /**
     * account.devices.push.update: the push token of the device asking (an app's only).
     */
    public function setPush(AppPushRequest $request, SetDevicePush $push): JsonResponse
    {
        $device = self::currentDevice($request);
        $environment = $request->validated('environment');
        $set = $push->set($device, (string) $request->validated('platform'), (string) $request->validated('token'), is_string($environment) ? $environment : null);

        return response()->json(['data' => AppTokens::view($set, $set)]);
    }

    /**
     * account.devices.push.delete: no more pushes to the device asking.
     */
    public function clearPush(Request $request, SetDevicePush $push): Response
    {
        $push->clear(self::currentDevice($request));

        return response()->noContent();
    }

    private static function currentDevice(Request $request): AppDevice
    {
        $device = $request->attributes->get(AuthenticateAppDevice::DEVICE);

        // Push is to an app's device: a browser's session has none.
        return $device instanceof AppDevice ? $device : throw new Refusal(403, 'app_only');
    }

    /**
     * The link that opened the app gives it the pass: Hillel's preview link, the mudeo email's
     * early-access link, the shared invite link, or a beta invite, while it still opens. Anything
     * else (paused, full, replaced, unknown) is the same 410.
     */
    public function claimPass(AppPassRequest $request): JsonResponse
    {
        $code = (string) $request->validated('code');
        $opens = match ($request->validated('kind')) {
            'preview' => EarlyAccess::opens($code, EarlyAccessLink::PREVIEW),
            'early' => EarlyAccess::opens($code, EarlyAccessLink::MUDEO),
            'invite' => EarlyAccess::opens($code, EarlyAccessLink::INVITE),
            default => AcceptInvite::opens($code),
        };

        if (! $opens) {
            throw new Refusal(410, 'link_closed');
        }

        $pass = BetaPass::appToken();
        $until = (int) explode('.', substr($pass, 4))[0];

        return response()->json(['data' => ['pass' => $pass, 'expiresAt' => Carbon::createFromTimestamp($until)->utc()->toIso8601ZuluString()]]);
    }
}
