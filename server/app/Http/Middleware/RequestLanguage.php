<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Languages\Languages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * An API request's language (docs/languages-plan.md): the signed-in account's own (any it may keep, so a draft can
 * be checked before its switch is on); otherwise the best of the browser's languages (Accept-Language, which the app
 * sets to the language it shows) among those open to everyone, else English. It's what a new account is given and
 * what a newcomer's sign-in link is written in. For this request only.
 */
final class RequestLanguage
{
    public function __construct(private readonly Languages $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $before = App::getLocale();
        $own = $request->user()?->locale;
        App::setLocale(is_string($own) && in_array($own, Languages::ALL, true) ? $own : ($request->getPreferredLanguage($this->languages->offered()) ?? 'en'));

        try {
            return $next($request);
        } finally {
            App::setLocale($before);
        }
    }
}
