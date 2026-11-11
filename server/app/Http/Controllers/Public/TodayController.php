<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Boards\Daily;
use App\Support\Pages\SongParts;
use Illuminate\Http\RedirectResponse;

/**
 * /today (docs/retention-plan.md, row 10): the day's part (Daily, the same for everyone) on its song part page, tagged
 * ?ref=today, so a link that never changes leads somewhere new each day. While that part has no page of its own (its
 * song isn't public domain, or no part pages), the songbook.
 */
final class TodayController extends Controller
{
    public function __invoke(SongParts $parts): RedirectResponse
    {
        $pick = Daily::pick(Daily::today());
        $path = $pick !== null ? '/songs/'.$pick['template']->slug.'/'.$pick['part'] : null;
        $to = $path !== null && SongParts::open() && in_array($path, $parts->paths(), true) ? $path : '/songs';

        // It changes at midnight UTC: a short cache, never a permanent redirect.
        return redirect()->to($to.'?ref=today', 302)->header('Cache-Control', 'public, max-age=300');
    }
}
