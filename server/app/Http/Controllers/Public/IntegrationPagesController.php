<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * The integrations (Hillel, 2026-11-09: pages that link to Event Schedule and Invoice Ninja, his other two products,
 * ahead of the integrations): /integrations, the hub, and a page for each (SitePages::INTEGRATIONS). Each says what
 * works side by side today and what the connection will do, which is built on our side against a fake and not open
 * (docs/event-schedule-integration-plan.md, docs/invoice-ninja-integration-plan.md), so the page says Coming. No
 * session; cached five minutes like the feature pages. Words: site.integrations.
 */
final class IntegrationPagesController extends Controller
{
    public function index(Request $request): Response
    {
        $hub = self::words('index');

        return response()->view('site.integrations', [
            ...self::frame((string) $hub['title'], (string) $hub['description'], '/integrations', $request),
            'pages' => array_map(static fn (string $slug): array => [...self::words('pages.'.$slug), 'slug' => $slug], SitePages::INTEGRATIONS),
        ], 200, SongPagesController::headers());
    }

    public function show(Request $request, string $slug): Response
    {
        if (! in_array($slug, SitePages::INTEGRATIONS, true)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, SongPagesController::headers());
        }

        $page = [...self::words('pages.'.$slug), 'slug' => $slug];

        return response()->view('site.integration', [
            ...self::frame((string) $page['title'], (string) $page['description'], '/integrations/'.$slug, $request),
            'page' => $page,
        ], 200, SongPagesController::headers());
    }

    /**
     * @return array{title: string, meta: array{title: string, description: string, url: string}, links: array{start: string, app: string, plus: string, create: string}}
     */
    private static function frame(string $title, string $description, string $path, Request $request): array
    {
        $title = SitePages::title($title);

        return [
            'title' => $title,
            'meta' => ['title' => $title, 'description' => $description, 'url' => Hosts::publicUrl().$path],
            'links' => Landing::links(Landing::ref($request->query('ref'))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function words(string $key): array
    {
        $words = Lang::get('site.integrations.'.$key);

        return is_array($words) ? $words : [];
    }
}
