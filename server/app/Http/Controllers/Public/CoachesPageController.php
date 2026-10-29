<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Coaching\CoachSearch;
use App\Http\Controllers\Controller;
use App\Models\CoachProfile;
use App\Support\Http\Hosts;
use Illuminate\Http\Response;

/**
 * /coaches (N4): the coach directory's public page, the frontend's view, while the directory is
 * open and the view exists.
 */
final class CoachesPageController extends Controller
{
    public function __invoke(CoachSearch $search): Response
    {
        if (! $search->isOpen() || ! is_file(resource_path('views/public/coaches.blade.php'))) {
            return response()->view('public.page-gone', ['links' => ['app' => '/songs']], 404, SongPagesController::headers());
        }

        return response()->view('public.coaches', [
            'coaches' => array_values(array_map(static fn (CoachProfile $p): array => CoachSearch::item($p), $search->search(null, [])->items())),
            'meta' => ['title' => __('pages.coaches.title'), 'description' => __('pages.coaches.description'), 'url' => Hosts::publicUrl().'/coaches'],
        ], 200, SongPagesController::headers());
    }
}
