<?php

declare(strict_types=1);

namespace App\Support\Web;

use App\Actions\Collaboration\ManageInvites;
use App\Exceptions\Refusal;
use App\Models\Group;
use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Sharing\SongCards;
use Illuminate\Http\Request;

/**
 * What a link to one of the app's pages shows when it's pasted into a chat (Slack, WhatsApp,
 * iMessage, X, Facebook): its title, a line, and a card. Read only, from the link alone: an invite
 * names the song and the part, never who sent it or the project's own title. The words are the
 * site's (lang/en/site.php, `meta.app`).
 */
final class AppMeta
{
    public function __construct(private readonly ManageInvites $invites, private readonly SongCards $cards) {}

    /**
     * @return array{title: string, description: string, image: string, imageAlt: string, url: string}
     */
    public function for(Request $request): array
    {
        $token = (string) $request->segment(2);
        $meta = match (true) {
            $request->is('join/*') => $this->join($token),
            $request->is('choir/*') => $this->choir($token),
            $request->is('invite/*') => $this->words('invite'),
            $request->is('early/*') => $this->words('early'),
            default => null,
        } ?? $this->words('default');

        // The canonical address is the page's own, without its query (a link's ?ref and the like).
        return [...$meta, 'url' => Hosts::appUrl().'/'.ltrim($request->path(), '/')];
    }

    /**
     * @return array{title: string, description: string, image: string, imageAlt: string}|null
     */
    private function join(string $token): ?array
    {
        try {
            $invite = $this->invites->open($token);
        } catch (Refusal) {
            return null;
        }

        $slug = $invite->project->template_slug;
        $song = $slug !== null ? Template::query()->listed()->with('currentVersion')->where('slug', $slug)->first() : null;

        // Their own song: its title is theirs, so the link says only what it's for.
        if ($song === null) {
            return [...$this->words('default'), 'description' => (string) __('site.meta.app.join.description')];
        }

        $part = $invite->part_name !== null && trim($invite->part_name) !== '' ? trim($invite->part_name) : null;

        return [
            'title' => $part !== null ? (string) __('site.meta.app.join.title_part', ['part' => $part, 'song' => $song->title]) : (string) __('site.meta.app.join.title', ['song' => $song->title]),
            'description' => (string) __('site.meta.app.join.description'),
            'image' => $this->cards->url($song, 'join'),
            'imageAlt' => (string) __('site.meta.app.join.image_alt', ['song' => $song->title]),
        ];
    }

    /**
     * @return array{title: string, description: string, image: string, imageAlt: string}|null
     */
    private function choir(string $token): ?array
    {
        $open = $token !== '' && Group::query()->where('link_hash', hash('sha256', $token))->exists();

        return $open ? $this->words('choir') : null;
    }

    /**
     * @return array{title: string, description: string, image: string, imageAlt: string}
     */
    private function words(string $page): array
    {
        return [
            'title' => (string) __('site.meta.app.'.$page.'.title'),
            'description' => (string) __('site.meta.app.'.$page.'.description'),
            'image' => SongCards::fallback(),
            'imageAlt' => (string) __('site.meta.app.default.image_alt'),
        ];
    }
}
