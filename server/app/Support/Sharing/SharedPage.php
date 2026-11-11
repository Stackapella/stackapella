<?php

declare(strict_types=1);

namespace App\Support\Sharing;

use App\Actions\Counts\RecordCounts;
use App\Support\Admin\StaffDevice;
use App\Support\Stats\Crawler;
use App\Support\Trust\Country;
use Illuminate\Http\Request;

/**
 * A shared link's page opened (docs/retention-plan.md, sharing: "a shared page opened"): one anonymous count by the
 * page's surface, so:<surface> (config counts.shared_pages), never the token, the song or who. A crawler's or a staff
 * device's load isn't counted.
 */
final class SharedPage
{
    public static function opened(Request $request, string $surface): void
    {
        if (Crawler::is($request) || StaffDevice::is($request)) {
            return;
        }

        app(RecordCounts::class)->server('so:'.$surface, $request->user() !== null, Country::of($request));
    }
}
