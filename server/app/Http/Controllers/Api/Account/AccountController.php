<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Account;

use App\Actions\Users\ChangeAvatar;
use App\Actions\Users\ChangeEmail;
use App\Actions\Users\DeleteAccount;
use App\Actions\Users\ManageSignInMethods;
use App\Actions\Users\RequestExport;
use App\Actions\Users\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateAppDevice;
use App\Http\Requests\Account\AcceptTermsRequest;
use App\Http\Requests\Account\AvatarRequest;
use App\Http\Requests\Account\ChangeEmailRequest;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Requests\Account\HandleCheckRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\AccountResource;
use App\Models\AppDevice;
use App\Models\DataExport;
use App\Models\User;
use App\Support\Accounts\HandleRules;
use App\Support\Accounts\Reauthentication;
use App\Support\Legal\Terms;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;

/**
 * account.*: the signed-in person's own account (contract/api.md, "Accounts and sign-in").
 */
final class AccountController extends Controller
{
    public function show(#[CurrentUser] User $user): AccountResource
    {
        return new AccountResource($user);
    }

    public function update(UpdateAccountRequest $request, UpdateProfile $update, #[CurrentUser] User $user): AccountResource
    {
        return new AccountResource($update->handle($user, $request->validated()));
    }

    /**
     * account.terms.accept: the new terms of use, accepted.
     */
    public function acceptTerms(AcceptTermsRequest $request, #[CurrentUser] User $user): AccountResource
    {
        return new AccountResource(Terms::accept($user, (int) $request->validated('version')));
    }

    public function handle(HandleCheckRequest $request, HandleRules $rules, #[CurrentUser] User $user): JsonResponse
    {
        $handle = (string) $request->validated('handle');
        $problem = $rules->problem($handle, $user);

        return response()->json(['data' => ['handle' => $handle, 'available' => $problem === null, 'reason' => $problem]]);
    }

    public function email(ChangeEmailRequest $request, ChangeEmail $change, Reauthentication $reauth, #[CurrentUser] User $user): JsonResponse
    {
        $reauth->ensure($request, $user, $request->validated('password'));
        $change->request($user, (string) $request->validated('email'));

        return response()->json(['data' => ['status' => 'check_email']], 202);
    }

    public function confirmEmail(TokenRequest $request, ChangeEmail $change): AccountResource
    {
        return new AccountResource($change->confirm((string) $request->validated('token'), $request->ip()));
    }

    public function revertEmail(TokenRequest $request, ChangeEmail $change): JsonResponse
    {
        $user = $change->revert((string) $request->validated('token'), $request->ip());

        // The address the link was sent to, so the page can send "Choose a new password" to it.
        return response()->json(['data' => ['email' => $user->email]]);
    }

    public function password(ChangePasswordRequest $request, ManageSignInMethods $methods, Reauthentication $reauth, #[CurrentUser] User $user): AccountResource
    {
        $reauth->ensure($request, $user, $request->validated('currentPassword'), 'currentPassword');
        $device = $request->attributes->get(AuthenticateAppDevice::DEVICE);
        $methods->setPassword($user, (string) $request->validated('password'), $request->session()->getId(), $device instanceof AppDevice ? $device->id : null);

        // A new password ends "remember me" elsewhere; this browser keeps its own (an app keeps its device).
        if (! $device instanceof AppDevice) {
            Auth::login($user, true);
        }

        return new AccountResource($user);
    }

    public function removeMethod(Request $request, string $method, ManageSignInMethods $methods, Reauthentication $reauth, #[CurrentUser] User $user): AccountResource
    {
        // Event Schedule as auth.config spells it, or as a path.
        $method = $method === 'event_schedule' ? 'event-schedule' : $method;
        abort_unless(in_array($method, ['google', 'apple', 'event-schedule', 'password'], true), 404);
        $reauth->ensure($request, $user, null);

        return new AccountResource($methods->remove($user, $method));
    }

    public function avatar(AvatarRequest $request, ChangeAvatar $avatar, #[CurrentUser] User $user): AccountResource
    {
        $file = $request->file('avatar');
        abort_if($file === null || is_array($file), 422);

        return new AccountResource($avatar->update($user, (string) $file->getRealPath()));
    }

    public function removeAvatar(ChangeAvatar $avatar, #[CurrentUser] User $user): AccountResource
    {
        return new AccountResource($avatar->remove($user));
    }

    public function requestExport(RequestExport $export, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->export($export->handle($user)->refresh())], 202);
    }

    public function showExport(RequestExport $export, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->export($export->latest($user))]);
    }

    public function destroy(DeleteAccountRequest $request, DeleteAccount $delete, Reauthentication $reauth, #[CurrentUser] User $user): Response
    {
        // Someone who hasn't answered the age question yet (the under-13 answer may come long after
        // a Google or Apple sign-in) may always delete.
        if ($user->age_confirmed_at !== null) {
            $reauth->ensure($request, $user, $request->validated('password'));
        }

        // Deleted while still signed in, so a failure on the way (Stripe, say) answers with the
        // error and they can try again. Then signed out without Auth::logout(), which would save a
        // new remember token and bring the deleted row back.
        $delete->handle($user, $request->ip());

        $guard = Auth::guard('web');
        Cookie::queue(Cookie::forget($guard->getRecallerName()));
        $guard->forgetUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function export(?DataExport $export): array
    {
        $ready = $export !== null && $export->status === 'ready' && $export->expires_at?->isFuture() && $export->file_key !== null;

        return [
            'status' => $export === null || ($export->status === 'ready' && ! $ready) ? 'none' : $export->status,
            'requestedAt' => $export?->created_at?->utc()->toIso8601ZuluString(),
            'readyAt' => $ready ? $export->ready_at?->utc()->toIso8601ZuluString() : null,
            'url' => $ready ? Storage::disk('private')->temporaryUrl((string) $export->file_key, $export->expires_at) : null,
            'expiresAt' => $ready ? $export->expires_at?->utc()->toIso8601ZuluString() : null,
        ];
    }
}
