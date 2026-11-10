<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\PageGone;
use App\Support\Pages\SongCollections;
use Illuminate\Http\Response;

/**
 * A song collection (/songs/<collection>, App\Support\Pages\SongCollections): its open songs as cards, as the carols
 * page shows them. With too few open songs, or none, a real 404 that points to /songs. No session; cached five minutes
 * like the song pages.
 */
final class SongCollectionPageController extends Controller
{
    public function __invoke(string $collection, SongCollections $collections): Response
    {
        $songs = $collections->songs($collection);

        if ($songs === []) {
            return PageGone::response('/songs');
        }

        return response()->view(SongCollections::VIEW, [
            'slug' => $collection,
            'songs' => $songs,
            'canonical' => Hosts::publicUrl().SongCollections::path($collection),
        ], 200, SongPagesController::headers());
    }
}
