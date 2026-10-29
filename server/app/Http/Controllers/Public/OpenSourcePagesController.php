<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Site\OpenSource;
use App\Support\Site\SitePages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * /open-source and /selfhost (docs/open-source-plan.md, 5.2 and 5.3). No session. While the
 * open_source flag is off they aren't there (a 404, never "Coming soon", which would announce it).
 */
final class OpenSourcePagesController extends Controller
{
    public function openSource(Request $request): Response
    {
        if (! OpenSource::on()) {
            return self::gone();
        }

        return response()->view('site.open-source', [
            ...self::frame('open_source', '/open-source', $request),
            'lists' => self::lists('open_source', ['open', 'quiet', 'npm', 'licence']),
            'code' => array_map(static fn (array $file): array => ['path' => (string) $file[0], 'what' => (string) $file[1], 'url' => OpenSource::repo((string) $file[0])], (array) Lang::get('site.open_source.code')),
        ], 200, SongPagesController::headers());
    }

    public function selfHost(Request $request): Response
    {
        if (! OpenSource::on()) {
            return self::gone();
        }

        return response()->view('site.selfhost', [
            ...self::frame('selfhost', '/selfhost', $request),
            'lists' => self::lists('selfhost', ['need', 'install', 'unlocked', 'missing', 'connects', 'yours']),
        ], 200, SongPagesController::headers());
    }

    /**
     * A page's lists of lines (`site.<page>.<list>`), for the view to loop over.
     *
     * @param  list<string>  $names
     * @return array<string, list<mixed>>
     */
    private static function lists(string $page, array $names): array
    {
        $lists = [];
        foreach ($names as $name) {
            $lists[$name] = array_values((array) Lang::get('site.'.$page.'.'.$name));
        }

        return $lists;
    }

    private static function gone(): Response
    {
        return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, DocumentPagesController::headers());
    }

    /**
     * @return array{title: string, meta: array{title: string, description: string, url: string}, links: array{start: string, app: string, plus: string, create: string}, repo: string}
     */
    private static function frame(string $key, string $path, Request $request): array
    {
        $title = SitePages::title((string) __('site.'.$key.'.title'));

        return [
            'title' => $title,
            'meta' => ['title' => $title, 'description' => (string) __('site.'.$key.'.description'), 'url' => Hosts::publicUrl().$path],
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'repo' => OpenSource::repo(),
        ];
    }
}
