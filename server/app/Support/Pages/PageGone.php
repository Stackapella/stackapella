<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Http\Controllers\Public\SongPagesController;
use Illuminate\Http\Response;

/**
 * The public site's 404 for an address that was never a page (public.page-gone): said plainly, with
 * a way on and back to the start, a real 404 status, and never indexed.
 */
final class PageGone
{
    public static function response(string $onward = '/'): Response
    {
        return response()->view('public.page-gone', ['links' => ['app' => $onward]], 404, [...SongPagesController::headers(), 'X-Robots-Tag' => 'noindex']);
    }
}
