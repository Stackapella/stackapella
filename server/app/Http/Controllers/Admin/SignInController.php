<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\Controller;
use App\Support\Admin\IntendedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The admin's sign-in page: one button, Sign in with Google (accounts for everyone come in M3).
 * After Google, the second factor; then the admin in the app at /admin.
 */
final class SignInController extends Controller
{
    /** The reasons the callback can send someone back here with, each worded in lang/en/admin.php. */
    public const ERRORS = ['cancelled', 'expired', 'failed', 'unverified', 'not_admin', 'suspended', 'blocked'];

    public function show(Request $request): View
    {
        $error = $request->query('error');

        return view('admin.auth.sign-in', [
            'intended' => IntendedUrl::admin($request->query('intended')),
            'error' => in_array($error, self::ERRORS, true) ? $error : null,
            'ended' => $request->boolean('ended'),
            'yourGoogleId' => $error === 'not_admin' ? $request->session()->get(ProviderController::YOUR_GOOGLE_ID) : null,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.sign-in')->with('status', 'signed_out');
    }
}
