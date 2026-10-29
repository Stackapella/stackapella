<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Choirs\Choirs;
use App\Actions\Ideas\Ideas;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Models\ChoirVoice;
use App\Models\IdeaVoice;
use App\Support\Gallery\GalleryViews;
use App\Support\Http\Hosts;
use App\Support\Site\SitePages;
use Illuminate\Http\Response;

/**
 * /ideas/<id> and /choirs/<song>: an idea waiting for voices (docs/your-tune-plan.md) and one of our
 * songs' virtual choir (N6), as pages a shared link opens, on the public host. The landing agent
 * writes the markup, this the data: what a signed-out visitor may see, with credits and the way
 * into the app, never a voice's audio. Not there while its flag is off, or for an idea the shelf
 * doesn't list (held, hidden, its writer suspended or under 18).
 */
final class SingTogetherPagesController extends Controller
{
    /** Singers credited on an idea's page, the newest first (as the choir page's 30). */
    public const MOST_CREDITS = 60;

    /** The credited singers a choir's page names, newest first. */
    private const CHOIR_CREDITS = 30;

    public function idea(string $idea, Ideas $ideas): Response
    {
        try {
            $found = $ideas->find($idea, null);
        } catch (Refusal) {
            return self::gone('/');
        }

        if (! $ideas->listed()->whereKey($found->id)->exists()) {
            return self::gone('/');
        }

        $view = $ideas->view($found, null);
        $voices = IdeaVoice::query()->with('user')->where('idea_id', $found->id)->where('status', IdeaVoice::READY)->whereNull('hidden_at')
            ->whereHas('user', fn ($user) => $user->whereNull('suspended_at'))->orderByDesc('id')->limit(self::MOST_CREDITS)->get();
        $url = Hosts::publicUrl().'/ideas/'.$found->public_id;
        $ref = 'idea-'.$found->public_id;

        return response()->view('public.idea', [
            'idea' => [
                ...collect($view)->only(['id', 'title', 'note', 'writer', 'voicing', 'parts', 'voices', 'complete', 'status', 'createdAt'])->all(),
                // "Sung by", per part, in the order the parts are listed.
                'credits' => $voices->map(fn (IdeaVoice $voice): array => ['partKey' => $voice->part_key, 'singer' => GalleryViews::person($voice->user)])->values()->all(),
            ],
            'meta' => [
                'title' => __('pages.sing_together.idea_title', ['title' => $view['title'], 'name' => $view['writer']['name']]),
                'description' => trans_choice('pages.sing_together.idea_description', (int) $view['voices'], ['name' => $view['writer']['name']]),
                'url' => $url,
                'image' => SitePages::image($url),
            ],
            // Into the app by a query, as /h/<id>'s "Learn this" does: /ideas/* on the app's host
            // would come back here.
            'links' => ['sing' => Hosts::app('/?'.http_build_query(['idea' => $found->public_id, 'ref' => $ref])), 'app' => Hosts::app('/?'.http_build_query(['ref' => $ref]))],
        ], 200, SongPagesController::headers());
    }

    public function choir(string $song, Choirs $choirs): Response
    {
        try {
            $choir = $choirs->choirFor($song);
        } catch (Refusal) {
            return self::gone('/songs');
        }

        $view = $choirs->show($song, null);
        $credited = $choir->exists
            ? Choirs::heard($choir)->with('user')->whereNotNull('in_mix_at')->where('credit', '!=', ChoirVoice::ANONYMOUS)
                ->whereHas('user', fn ($user) => $user->whereNull('suspended_at'))->orderByDesc('id')->limit(self::CHOIR_CREDITS)->get()
            : collect();
        $url = Hosts::publicUrl().'/choirs/'.$song;
        $ref = 'choir-'.$song;

        return response()->view('public.choir', [
            'choir' => [
                ...collect($view)->only(['song', 'bpm', 'key', 'mixUrl', 'mixedAt', 'voices', 'parts'])->all(),
                // "Sung by": those who chose to be credited, up to 30 (the rest are in the count).
                'credits' => $credited->map(fn (ChoirVoice $voice): array => ['partKey' => $voice->part_key, 'singer' => GalleryViews::person($voice->user)])->values()->all(),
            ],
            'meta' => [
                'title' => __('pages.sing_together.choir_title', ['title' => $view['song']['title']]),
                'description' => trans_choice('pages.sing_together.choir_description', (int) $view['voices'], ['title' => $view['song']['title']]),
                'url' => $url,
                'image' => SitePages::image($url),
                'audio' => $view['mixUrl'],
            ],
            'links' => ['sing' => Hosts::app('/?'.http_build_query(['choir' => $song, 'ref' => $ref])), 'song' => Hosts::public('/songs/'.$song), 'app' => Hosts::app('/?'.http_build_query(['ref' => $ref]))],
        ], 200, SongPagesController::headers());
    }

    private static function gone(string $app): Response
    {
        return response()->view('public.page-gone', ['links' => ['app' => $app]], 404, SongPagesController::headers());
    }
}
