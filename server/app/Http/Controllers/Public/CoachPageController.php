<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Support\Coaching\CoachDirectory;
use App\Support\Http\Hosts;
use Illuminate\Http\Response;

/**
 * /coach/<handle>: a coach's public page, reached by their own link (contract/api.md, "Public
 * pages: /coach/<handle>"). No session and no cookies; never indexed until the directory opens.
 * Buying happens in the app.
 */
final class CoachPageController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'public, max-age=300', 'X-Robots-Tag' => 'noindex'];

    public function __invoke(string $handle): Response
    {
        try {
            $coach = CoachDirectory::publicCoach($handle);
        } catch (Refusal) {
            // Not a coach anyone can buy from now: the same page for every reason.
            return response()->view('public.coach-gone', ['links' => ['app' => '/']], 404, self::HEADERS);
        }

        $url = Hosts::publicUrl().'/coach/'.$coach['handle'];

        return response()->view('public.coach', [
            'coach' => $coach,
            'meta' => [
                'title' => __('coaching.page.title', ['name' => $coach['name']]),
                'description' => mb_strimwidth($coach['bio'], 0, 200, '...'),
                'url' => $url,
                'image' => $coach['avatarUrl'],
            ],
            'links' => ['app' => Hosts::app('/?'.http_build_query(['coach' => $coach['handle'], 'ref' => 'c-'.$coach['handle']]))],
        ], 200, self::HEADERS);
    }
}
