<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Counts\RecordCounts;
use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\PageGone;
use App\Support\Pages\Views;
use Illuminate\Http\Response;

/**
 * A free tool for other sites to embed (docs/seo-growth-plan.md, item 2): /embed/tools/<slug> for
 * the tools in config('tools.embeddable'), framed by any site (SecurityHeaders), with no session
 * and no cookies. Its try-the-app link carries ?ref=embed-<the embedding site's host>, which the
 * page reads from its referrer, so the counts add it up by site and never by person. A real 404
 * for any other tool, and for every one until the embed's view (embed.tool) exists.
 */
final class ToolEmbedController extends Controller
{
    public const VIEW = 'embed.tool';

    public function __invoke(string $tool): Response
    {
        if (! in_array($tool, (array) config('tools.embeddable'), true) || ! Views::has(self::VIEW)) {
            return PageGone::response('/tools');
        }

        return response()->view(self::VIEW, ['embed' => [
            'tool' => $tool,
            'name' => (string) __('tools.names.'.$tool),
            'pageUrl' => Hosts::public('/tools/'.$tool),
            'appUrl' => Hosts::app('/'),
            'refPrefix' => RecordCounts::EMBED_PREFIX,
            'listens' => in_array($tool, (array) config('tools.listening'), true),
        ]], 200, SongPagesController::headers());
    }
}
