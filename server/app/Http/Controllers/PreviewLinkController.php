<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EarlyAccessLink;
use App\Support\Accounts\BetaPass;
use App\Support\Accounts\EarlyAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hillel's preview link (/preview/<code>): the app before launch, without an account. An open
 * code shows one button (so a link preview never counts as a visit), whose POST gives the beta
 * pass, counts it and goes to the app; someone already holding the pass goes straight in. A
 * paused or replaced code shows "coming soon".
 */
final class PreviewLinkController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex', 'Referrer-Policy' => 'no-referrer'];

    public function show(Request $request, string $code): Response
    {
        $link = EarlyAccess::find($code, EarlyAccessLink::PREVIEW);

        if ($link === null || $link->status() !== 'open') {
            return response()->view('coming-soon', [], 200, self::HEADERS);
        }

        if (BetaPass::valid($request)) {
            return redirect('/?app')->withHeaders(self::HEADERS);
        }

        return response()->view('pages.preview', ['title' => __('meta.preview.title'), 'action' => $request->url()], 200, self::HEADERS);
    }

    public function open(Request $request, string $code): Response
    {
        $link = EarlyAccess::find($code, EarlyAccessLink::PREVIEW);

        if ($link === null || $link->status() !== 'open') {
            return response()->view('coming-soon', [], 200, self::HEADERS);
        }

        if (! BetaPass::valid($request)) {
            EarlyAccessLink::query()->whereKey($link->id)->increment('joined');
        }

        $response = redirect('/?app')->withHeaders(self::HEADERS);
        // (Never the mudeo arrival mark: the preview goes to people who aren't from mudeo.)
        $response->headers->setCookie(BetaPass::cookie($request));

        return $response;
    }
}
