<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Gallery\Explore;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Http\Hosts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /@<handle> and /explore (N1): the gallery's public pages (the frontend's markup, this the data).
 * No session: everything a signed-out visitor would see. Not there while the gallery is closed.
 */
final class GalleryPagesController extends Controller
{
    public function __construct(private readonly Gallery $gallery, private readonly GalleryViews $views) {}

    public function profile(string $handle, Explore $explore): Response
    {
        $user = User::query()->where('handle', strtolower($handle))->first();

        if (! $this->gallery->isOpen() || $user === null || ! Gallery::hasPublicProfile($user)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/explore']], 404, SongPagesController::headers());
        }

        $profile = $this->views->profile($user, null);

        return response()->view('public.profile', [
            'profile' => $profile,
            'harmonies' => $explore->list(null, 'new', null, null, $user->id)['data'],
            'meta' => [
                'title' => __('pages.gallery.profile_title', ['name' => $profile['name']]),
                'description' => trans_choice('pages.gallery.profile_description', (int) $profile['harmonies'], ['name' => $profile['name']]),
                'url' => Hosts::publicUrl().'/@'.$user->handle,
            ],
        ], 200, SongPagesController::headers());
    }

    public function explore(Request $request, Explore $explore): Response
    {
        if (! $this->gallery->isOpen()) {
            return response()->view('public.page-gone', ['links' => ['app' => '/songs']], 404, SongPagesController::headers());
        }

        $tab = $request->query('tab') === 'featured' ? 'featured' : 'new';

        return response()->view('public.explore', [
            'tab' => $tab,
            'harmonies' => $explore->list(null, $tab, null, null)['data'],
            'meta' => ['title' => __('pages.gallery.explore_title'), 'description' => __('pages.gallery.explore_description'), 'url' => Hosts::publicUrl().'/explore'],
        ], 200, SongPagesController::headers());
    }
}
