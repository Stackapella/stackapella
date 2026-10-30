<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Counts;

use App\Actions\Counts\RecordCounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Counts\RecordCountsRequest;
use App\Support\Pages\MudeoArrival;
use App\Support\Trust\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Anonymous usage counts (config/counts.php): counts.create adds to today's numbers and keeps
 * nothing else; admin.counts.show is the admin's Usage panel.
 */
final class CountsController extends Controller
{
    public function store(RecordCountsRequest $request, RecordCounts $counts): Response
    {
        if (config('counts.enabled') === true) {
            /** @var list<array{kind: string, n?: int}> $events */
            $events = array_values((array) $request->validated('events'));
            $variant = $request->validated('variant');
            $source = $request->validated('source');
            $counts->handle($events, $request->boolean('signedIn'), Country::of($request), is_string($variant) ? $variant : null, is_string($source) ? $source : null);

            // Someone who came from the mudeo email starting to sing: once a browser visit, a
            // number only (the visit's cookie holds no person).
            if (MudeoArrival::is($request, null) && $request->cookies->get(MudeoArrival::USED_COOKIE) !== '1'
                && array_intersect(array_column($events, 'kind'), ['song_created', 'lesson_done']) !== []) {
                $counts->server('mudeo_used', $request->boolean('signedIn'), Country::of($request));

                return response()->noContent()->withCookie(MudeoArrival::cookie($request, MudeoArrival::USED_COOKIE));
            }
        }

        return response()->noContent();
    }

    public function show(Request $request, RecordCounts $counts): JsonResponse
    {
        // 1: today so far (UTC), for the page's near-live view.
        $days = in_array((int) $request->query('days', '30'), [1, 7, 30, 90], true) ? (int) $request->query('days', '30') : 30;

        return response()->json(['data' => $counts->summary($days)]);
    }
}
