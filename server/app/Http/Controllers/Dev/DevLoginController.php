<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dev\DevLoginRequest;
use App\Models\User;
use App\Support\Admin\AdminGate;
use App\Support\Admin\IntendedUrl;
use App\Support\Admin\SecondFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Local only: Google won't accept a local redirect address, and the flow scripts can't use a
 * passkey. Pick a user to sign in as, as if they had just signed in with Google and passed the
 * second factor. The routes exist only when APP_ENV=local, and the constructor refuses anything
 * else as well.
 */
final class DevLoginController extends Controller
{
    public function __construct()
    {
        abort_unless(app()->environment('local'), 404);
    }

    public function index(Request $request): View
    {
        return view('dev.login', [
            'users' => User::query()->orderByDesc('is_admin')->orderByDesc('is_editor')->orderBy('id')->limit(40)->get(),
            'intended' => IntendedUrl::admin($request->query('intended')),
        ]);
    }

    public function store(DevLoginRequest $request): RedirectResponse
    {
        $user = User::query()->findOrFail((int) $request->validated('user_id'));

        $request->session()->invalidate();
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $request->session()->put([
            AdminGate::SESSION_EMAIL => strtolower((string) $user->email),
            AdminGate::SESSION_FRESH => true,
            SecondFactor::SESSION_PASSED_AT => now()->getTimestamp(),
            SecondFactor::SESSION_USER => (int) $user->getKey(),
        ]);

        return redirect()->to(IntendedUrl::admin($request->validated('intended')));
    }
}
