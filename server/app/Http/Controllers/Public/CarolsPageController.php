<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Carols;
use App\Support\Pages\PageGone;
use Illuminate\Http\Response;

/**
 * The Christmas carols page (/songs/christmas-carols, App\Support\Pages\Carols): every open carol and its parts' pages.
 * With no carol open, or no part pages yet, a real 404. No session; cached five minutes like the song pages.
 */
final class CarolsPageController extends Controller
{
    public function __invoke(Carols $carols): Response
    {
        $songs = $carols->songs();

        if ($songs === []) {
            return PageGone::response('/songs');
        }

        return response()->view(Carols::VIEW, [
            'carols' => $songs,
            'weekly' => Carols::weekly(),
            // Hillel's redesign (2026-11-10): the festive look only while the carols are pushed, the countdown and
            // tonight's carol only up to Christmas Day; the page stays plain the rest of the year.
            'festive' => Carols::promoted(),
            'countdown' => Carols::countdown(),
            'tonight' => Carols::tonight($songs),
            'totals' => Carols::totals($songs),
            'canonical' => Hosts::publicUrl().Carols::PATH,
        ], 200, SongPagesController::headers());
    }
}
