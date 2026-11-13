<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\Lessons;
use App\Support\Site\Correction;
use App\Support\Site\SitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * The Plus courses' pages (docs/plus-courses-plan.md): /courses and /courses/<slug>, each there once
 * its words are written. No session; cached five minutes like the song pages.
 */
final class CoursePagesController extends Controller
{
    public function index(Request $request): Response
    {
        if (SitePages::courses() === []) {
            return response()->view('public.page-gone', ['links' => ['app' => '/features']], 404, SongPagesController::headers());
        }

        $index = (array) Lang::get('site.courses.index');

        return response()->view('site.courses', [
            'meta' => ['title' => SitePages::title((string) ($index['title'] ?? '')), 'description' => (string) ($index['description'] ?? ''), 'url' => Hosts::publicUrl().'/courses'],
            'groups' => SitePages::courseGroups(),
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'draw' => SitePages::draw(),
        ], 200, SongPagesController::headers());
    }

    /**
     * The free course has one canonical page (marketing, 2026-11-07); its lesson pages sit under /courses/harmony.
     * The visitor's query goes with them (a shared link's ref).
     */
    public function free(Request $request): RedirectResponse
    {
        return new RedirectResponse(Landing::withQuery($request, '/features/course'), 301);
    }

    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        if (isset(SitePages::REMOVED_COURSES[$slug])) {
            return redirect(Landing::withQuery($request, SitePages::REMOVED_COURSES[$slug]), 301);
        }

        if (! in_array($slug, SitePages::courses(), true)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/courses']], 404, SongPagesController::headers());
        }

        $page = SitePages::page('courses', $slug);
        // Its group by the app's catalogue, so the site and Home agree.
        $page['group'] = SitePages::courseGroup($slug) ?? ($page['group'] ?? null);

        Correction::share('course', (string) ($page['name'] ?? $page['headline'] ?? $slug));

        return response()->view('site.course', [
            'title' => SitePages::title((string) $page['title']),
            'meta' => ['title' => SitePages::title((string) $page['title']), 'description' => (string) $page['description'], 'url' => Hosts::publicUrl().'/courses/'.$slug],
            'page' => $page,
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            // Start the first lesson: straight to it in the app, tagged as from this page unless the visitor brought a
            // tag (the coordinator, 2026-11-08); a course without lesson pages opens its map.
            'courseStart' => Lessons::firstLesson($slug, Landing::ref($request->query('ref')) ?? 'course-page')
                ?? Hosts::app('/learn/c/'.(string) ($page['app'] ?? $slug).'?'.http_build_query(['ref' => Landing::ref($request->query('ref')) ?? 'course-page'])),
            'draw' => SitePages::draw(),
        ], 200, SongPagesController::headers());
    }
}
