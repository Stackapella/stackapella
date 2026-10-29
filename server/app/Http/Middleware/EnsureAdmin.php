<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Admin\AdminGate;
use App\Support\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only routes, pages and API alike: the three-check rule (App\Support\Admin\AdminGate), the
 * session tied to the browser's User-Agent, and idle and absolute session limits. The API answers
 * with codes (contract/api.md, admin.me); pages redirect to the sign-in page.
 */
class EnsureAdmin
{
    public function __construct(protected readonly AdminGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->signIn($request);
        }

        if (! $this->allowed($user, $request)) {
            return $this->isApi($request)
                ? ApiError::response(403, 'not_admin')
                : response()->view('admin.auth.not-admin', [], 403);
        }

        $session = $request->session();
        $agent = AdminGate::userAgentHash($request->userAgent());

        if ($session->get(AdminGate::SESSION_STARTED_AT) === null) {
            $session->put([AdminGate::SESSION_STARTED_AT => now()->getTimestamp(), AdminGate::SESSION_UA => $agent]);
        }

        if (! $this->gate->sessionIsFresh($session, $agent)) {
            Auth::logoutCurrentDevice();
            $session->invalidate();
            $session->regenerateToken();

            return $this->signIn($request, ended: true);
        }

        $session->put(AdminGate::SESSION_LAST_SEEN_AT, now()->getTimestamp());

        return $next($request);
    }

    protected function allowed(User $user, Request $request): bool
    {
        return $this->gate->isAdmin($user, $request->session());
    }

    private function signIn(Request $request, bool $ended = false): Response
    {
        if ($this->isApi($request)) {
            return ApiError::response(401, 'unauthenticated');
        }

        // Back to the page itself, or for a button (a POST) the page it was pressed on.
        $intended = $request->isMethod('GET') ? $request->getRequestUri() : (string) parse_url(url()->previous(), PHP_URL_PATH);

        return redirect()->route('admin.sign-in', array_filter(['intended' => $intended, 'ended' => $ended ? 1 : null]));
    }

    private function isApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
