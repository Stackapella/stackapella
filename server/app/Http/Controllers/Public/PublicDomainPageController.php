<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\PublicDomain;
use Illuminate\Http\Response;

/**
 * The songs in the public domain, and the songs that will be (/songs/public-domain, App\Support\Pages\PublicDomain): a
 * roll of the people who made them, by the 1 January each one's work joins the public domain. The same page for every
 * visitor (each country's state is written out, never picked by the visitor's), so the edge may keep it like the song
 * pages. No session.
 */
final class PublicDomainPageController extends Controller
{
    public function __invoke(PublicDomain $publicDomain): Response
    {
        return response()->view(PublicDomain::VIEW, [
            'page' => $publicDomain->page(),
            'canonical' => Hosts::publicUrl().PublicDomain::PATH,
        ], 200, SongPagesController::edgeHeaders());
    }
}
