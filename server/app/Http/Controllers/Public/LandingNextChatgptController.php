<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Admin\StaffDevice;
use App\Support\Http\Hosts;
use App\Support\Languages\Languages;
use App\Support\Pages\LandingNextChatgpt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * /next-chatgpt and /es/next-chatgpt: a candidate landing page under test beside / (App\Support\Pages\LandingNextChatgpt).
 * No session and no cookies, as every public page; never indexed, whatever the site's own switch says.
 */
final class LandingNextChatgptController extends Controller
{
    public function __invoke(Request $request, Languages $languages, string $language = 'en'): Response
    {
        if ($language !== 'en') {
            // Its other languages answer as the site's pages in them do: once the language is open, and before that
            // only locally, in tests and on an admin's device.
            abort_unless(in_array($language, LandingNextChatgpt::LANGUAGES, true), 404);
            abort_unless($languages->isOffered($language) || App::environment(['local', 'testing']) || StaffDevice::previews($request), 404);
        }

        // The page's language for this request only: a long-running worker serves the next one as it was.
        $before = App::getLocale();
        App::setLocale($language);

        try {
            $html = view('pages.landing-next-chatgpt', [
                'title' => __('next_chatgpt.title'),
                'description' => __('next_chatgpt.description'),
                'url' => Hosts::publicUrl().($language === 'en' ? '' : '/'.$language).LandingNextChatgpt::PATH,
                'language' => $language,
                'stage' => LandingNextChatgpt::stage($request),
                'links' => LandingNextChatgpt::links($language),
                'languages' => LandingNextChatgpt::languages($language),
                'counts' => LandingNextChatgpt::counts(),
                'visit' => LandingNextChatgpt::visit($request),
            ])->render();
        } finally {
            App::setLocale($before);
        }

        // The song may be withheld by country, so no shared cache keeps one country's page for another.
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Language' => $language,
            'Cache-Control' => 'no-cache',
            'Vary' => 'CF-IPCountry',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
