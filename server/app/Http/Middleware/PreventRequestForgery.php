<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as LaravelPreventRequestForgery;

/**
 * Laravel's CSRF check, plus one way in for the app's sign-in forms (contract/api.md, "Sign-in
 * navigations"): a form POST can't send the X-XSRF-TOKEN header, and the app has only the
 * XSRF-TOKEN cookie (encrypted), so `_token` may carry that cookie's value too.
 */
final class PreventRequestForgery extends LaravelPreventRequestForgery
{
    /**
     * A native app's request carries no cookie (AuthenticateAppDevice drops any), so it can't be
     * forged from another site: no CSRF check.
     */
    public function handle($request, Closure $next)
    {
        if ($request->attributes->get(AuthenticateAppDevice::APP) === true) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    protected function getTokenFromRequest($request)
    {
        $field = $request->input('_token');

        // A plain token is 40 characters; the cookie's value is an encrypted payload.
        if (is_string($field) && strlen($field) > 100) {
            try {
                return CookieValuePrefix::remove($this->encrypter->decrypt($field, self::serialized()));
            } catch (DecryptException) {
                return '';
            }
        }

        return parent::getTokenFromRequest($request);
    }
}
