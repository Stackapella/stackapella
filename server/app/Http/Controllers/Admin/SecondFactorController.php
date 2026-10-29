<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PasskeyCredentialRequest;
use App\Http\Requests\Admin\SecondFactorCodeRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Admin\SecondFactorAdded;
use App\Support\Admin\Admins;
use App\Support\Admin\IntendedUrl;
use App\Support\Admin\SecondFactor;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * The admin second factor (iaw's). Setup is forced the first time someone opens admin (no
 * skipping), and every admin session needs a check: a passkey (phishing-resistant), or an
 * authenticator-app code or one-time recovery code as the backup.
 */
final class SecondFactorController extends Controller
{
    public function __construct(
        private readonly SecondFactor $secondFactor,
        private readonly Admins $admins,
    ) {}

    public function setup(Request $request, #[CurrentUser] User $user): View|RedirectResponse
    {
        if ($this->secondFactor->isEnrolled($user) && ! $request->session()->has('admin.recovery_codes')) {
            return redirect()->route('admin.second-factor.challenge');
        }

        $secret = $request->session()->get('admin.totp_pending');
        $secret = is_string($secret) ? $secret : $this->secondFactor->newTotpSecret();
        $request->session()->put('admin.totp_pending', $secret);

        return view('admin.auth.second-factor-setup', [
            'secret' => $secret,
            'qr' => $this->qrSvg($this->secondFactor->totpUri($user, $secret)),
            'recoveryCodes' => $request->session()->get('admin.recovery_codes'),
        ]);
    }

    public function registrationOptions(Request $request, GenerateRegistrationOptions $generate, #[CurrentUser] User $user): JsonResponse
    {
        if (! $this->mayEnrol($request, $user)) {
            return $this->confirmFirst();
        }

        $options = $generate($user);
        $request->session()->put('admin.passkey_registration', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function storePasskey(PasskeyCredentialRequest $request, StorePasskey $store, #[CurrentUser] User $user): JsonResponse
    {
        if (! $this->mayEnrol($request, $user)) {
            return $this->confirmFirst();
        }

        $enrolled = $this->secondFactor->isEnrolled($user);
        $serialized = $request->session()->pull('admin.passkey_registration');

        if (! is_string($serialized)) {
            return response()->json(['message' => __('admin.second_factor.too_slow')], 422);
        }

        try {
            $passkey = $store(
                $user,
                (string) ($request->validated('name') ?: $this->deviceName((string) $request->userAgent())),
                WebAuthn::fromJson((string) json_encode($request->validated('credential')), PublicKeyCredential::class),
                WebAuthn::fromJson($serialized, PublicKeyCredentialCreationOptions::class),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('admin.second_factor.passkey_not_saved')], 422);
        }

        AuditLog::record('admin.passkey_registered', $user, $user, after: ['name' => $passkey->name], ip: $request->ip());

        // Someone already enrolled proved it with an existing factor (mayEnrol), so a new passkey
        // never counts as the check itself; every admin hears about the extra factor.
        if ($enrolled) {
            Notification::send($this->admins->all(), new SecondFactorAdded($user, 'passkey', (string) $request->userAgent()));
        } else {
            $this->secondFactor->markPassed($request->session(), $user, 'passkey', (string) $request->userAgent(), $request->ip());
        }

        return response()->json(['redirect' => $this->intended($request)]);
    }

    public function confirmTotp(SecondFactorCodeRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        if (! $this->mayEnrol($request, $user)) {
            return redirect()->route('admin.second-factor.challenge', ['sudo' => 1]);
        }

        $enrolled = $this->secondFactor->isEnrolled($user);
        $secret = (string) $request->session()->get('admin.totp_pending');

        if ($secret === '' || ! $this->secondFactor->verifyTotpCode($secret, (string) $request->validated('code'))) {
            throw ValidationException::withMessages(['code' => __('admin.second_factor.code_mismatch')]);
        }

        $factor = $this->secondFactor->enableTotp($user, $secret);
        $codes = $this->secondFactor->newRecoveryCodes($factor);
        $request->session()->forget('admin.totp_pending');
        $request->session()->put('admin.recovery_codes', $codes);

        AuditLog::record('admin.totp_enabled', $user, $user, ip: $request->ip());

        if ($enrolled) {
            Notification::send($this->admins->all(), new SecondFactorAdded($user, 'totp', (string) $request->userAgent()));
        } else {
            $this->secondFactor->markPassed($request->session(), $user, 'code', (string) $request->userAgent(), $request->ip());
        }

        return redirect()->route('admin.second-factor.setup');
    }

    /**
     * After the recovery codes have been shown (once), continue to admin.
     */
    public function finishSetup(Request $request): RedirectResponse
    {
        $request->session()->forget('admin.recovery_codes');

        return redirect()->to($this->intended($request));
    }

    public function challenge(Request $request, #[CurrentUser] User $user): View|RedirectResponse
    {
        if (! $this->secondFactor->isEnrolled($user)) {
            return redirect()->route('admin.second-factor.setup');
        }

        // Sent here by the app for a sensitive action: back to that admin page afterwards.
        if ($request->filled('intended')) {
            $request->session()->put('url.intended', IntendedUrl::admin($request->query('intended')));
        } elseif (! $request->session()->has('url.intended') && is_string($referer = $request->headers->get('referer')) && str_starts_with($referer, url('/admin/'))) {
            $request->session()->put('url.intended', IntendedUrl::admin(substr($referer, strlen(url('')))));
        }

        return view('admin.auth.second-factor-challenge', [
            'hasPasskey' => $user->hasPasskeysEnabled(),
            'hasTotp' => (bool) $user->secondFactor?->hasTotp(),
            'locked' => (bool) $user->secondFactor?->isLocked(),
            'sudo' => $request->boolean('sudo'),
        ]);
    }

    public function verificationOptions(Request $request, GenerateVerificationOptions $generate, #[CurrentUser] User $user): JsonResponse
    {
        $options = $generate($user);
        $request->session()->put('admin.passkey_verification', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function verifyPasskey(PasskeyCredentialRequest $request, VerifyPasskey $verify, #[CurrentUser] User $user): JsonResponse
    {
        $serialized = $request->session()->pull('admin.passkey_verification');

        if (! is_string($serialized)) {
            return response()->json(['message' => __('admin.second_factor.too_slow')], 422);
        }

        try {
            $verify(
                WebAuthn::fromJson((string) json_encode($request->validated('credential')), PublicKeyCredential::class),
                WebAuthn::fromJson($serialized, PublicKeyCredentialRequestOptions::class),
                $user,
            );
        } catch (Throwable) {
            // A wrong or foreign passkey is an expected answer, not an error to report.
            $this->secondFactor->passkeyFailed($user, $request->ip());

            return response()->json(['message' => __('admin.second_factor.passkey_failed')], 422);
        }

        $this->secondFactor->markPassed($request->session(), $user, 'passkey', (string) $request->userAgent(), $request->ip());

        return response()->json(['redirect' => $this->intended($request)]);
    }

    public function verifyCode(SecondFactorCodeRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $result = $this->secondFactor->attempt($user, (string) $request->validated('code'), $request->ip());

        if ($result !== 'ok') {
            throw ValidationException::withMessages(['code' => $result === 'locked'
                ? __('admin.second_factor.locked')
                : __('admin.second_factor.code_failed')]);
        }

        $this->secondFactor->markPassed($request->session(), $user, 'code', (string) $request->userAgent(), $request->ip());

        return redirect()->to($this->intended($request));
    }

    /**
     * Adding a factor: free for someone setting up their first one, otherwise only right after a
     * check with an existing factor. Otherwise a stolen Google sign-in could enrol its own passkey
     * and walk straight past the second factor.
     */
    private function mayEnrol(Request $request, User $user): bool
    {
        return ! $this->secondFactor->isEnrolled($user) || $this->secondFactor->inSudoWindow($request->session(), $user);
    }

    /**
     * The JSON answer when a fresh check is needed first: passkeys.js follows `confirm`.
     */
    private function confirmFirst(): JsonResponse
    {
        return response()->json([
            'message' => __('admin.second_factor.confirm_first'),
            'confirm' => route('admin.second-factor.challenge', ['sudo' => 1]),
        ], 423);
    }

    private function intended(Request $request): string
    {
        return IntendedUrl::admin($request->session()->pull('url.intended'));
    }

    private function qrSvg(string $uri): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);
    }

    private function deviceName(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Windows') => 'Windows',
            default => __('admin.second_factor.passkey'),
        };
    }
}
