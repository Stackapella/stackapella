<?php

declare(strict_types=1);

namespace App\Support\Sharing;

use App\Models\AiVoice;
use App\Models\Harmony;
use App\Models\HarmonyConsent;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Http\Hosts;
use App\Support\Moderation\TextFilter;
use Illuminate\Support\Facades\Storage;

/**
 * What a shared harmony says about itself, the same on its page, its card and its video: the
 * title (or "Untitled song"), who sang it, the song and its credits. All from the server's own
 * records, never from the request (contract/api.md, "Sharing").
 */
final class HarmonyFacts
{
    public function title(Harmony $harmony): string
    {
        $title = trim($harmony->title);

        return $title === '' || $harmony->title_held_at !== null ? __('sharing.untitled') : $title;
    }

    /**
     * @return list<array{name: string, avatarUrl: string|null}>
     */
    public function singers(Harmony $harmony): array
    {
        // The owner first, then the friends who sang on it, in the order they joined Stackapella.
        $others = ($harmony->singer_ids ?? []) === [] ? [] : User::query()->whereKey($harmony->singer_ids)->orderBy('id')->get(['id', 'name', 'avatar_key', 'hide_profile', 'adult_confirmed_at'])->all();
        // A friend is named, with their picture, only once they've agreed for this song (which needs
        // them 18 or over): the link may be embedded anywhere (security review, 2026-11-01).
        $agreed = $others === [] ? [] : HarmonyConsent::query()->where('harmony_id', $harmony->id)->where('status', HarmonyConsent::AGREED)->pluck('user_id')->flip()->all();
        $named = static fn (User $singer): bool => $singer->id === $harmony->user_id || (isset($agreed[$singer->id]) && Gallery::adult($singer));

        return array_map(static fn (User $singer): array => [
            // A name is shown on a public page, a card and a video: "Anonymous" for a hidden
            // profile, and one with a link or a word-list match is never drawn there.
            'name' => ! $named($singer) ? __('sharing.a_singer') : (Gallery::hidden($singer) ? __('sharing.anonymous') : (TextFilter::hasLink($singer->name) || TextFilter::holds($singer->name) ? __('sharing.a_singer') : $singer->name)),
            'avatarUrl' => $named($singer) && $singer->avatar_key !== null && ! Gallery::hidden($singer) ? Storage::disk('media')->url($singer->avatar_key) : null,
        ], [$harmony->user, ...$others]);
    }

    public function singersLine(Harmony $harmony): string
    {
        return __(match ($this->played($harmony)) {
            'instrument' => 'sharing.played_by',
            'both' => 'sharing.sung_and_played_by',
            default => 'sharing.sung_by',
        }, ['names' => $this->names($harmony)]);
    }

    /**
     * What its tracks are (docs/instrument-parts-plan.md): all voices (a share made before
     * instruments too), all instruments, or some of each, for "Sung by", "Played by" or "Sung and
     * played by".
     *
     * @return 'voice'|'instrument'|'both'
     */
    public function played(Harmony $harmony): string
    {
        $kinds = array_unique(array_map(static fn (array $track): string => ($track['kind'] ?? 'voice') === 'instrument' ? 'instrument' : 'voice', $harmony->tracks));

        return count($kinds) > 1 ? 'both' : ($kinds === ['instrument'] ? 'instrument' : 'voice');
    }

    /**
     * The card's and the link previews' alt text: the title, and who sang or played it.
     */
    public function imageAlt(Harmony $harmony): string
    {
        return __(match ($this->played($harmony)) {
            'instrument' => 'sharing.image_alt_played',
            'both' => 'sharing.image_alt_both',
            default => 'sharing.image_alt',
        }, ['title' => $this->title($harmony), 'names' => $this->names($harmony)]);
    }

    public function names(Harmony $harmony): string
    {
        $names = array_column($this->singers($harmony), 'name');
        $last = array_pop($names);

        return $names === [] ? (string) $last : __('sharing.and', ['first' => implode(', ', $names), 'last' => (string) $last]);
    }

    /**
     * The beta singers whose published voices are in the mix (their licence covers it,
     * docs/backend-plan.md D5). A voice removed since is left out. An AI part comes as the facts for the view to word in
     * the shared label (design rule "AI: plain about it, never loud"): `ai`, no `name`, and `aiBasedOn` only when its
     * singer chose a credit. A part that's an AI voice in the sharer's own voice (Hear yourself as a choir) comes with
     * `ownVoice` too, and `aiBasedOn` the sharer's name as the page shows it.
     *
     * A friend's part in it (choir together) has `friend` too, its `aiBasedOn` their first name only if they chose to show it.
     *
     * @return list<array{name: string|null, part: string, ai: bool, aiBasedOn: string|null, ownVoice: bool, friend?: bool}>
     */
    public function guides(Harmony $harmony): array
    {
        // Made from the sharer's own singing: named as the page names them (none when their profile is hidden or their
        // name can't be shown, and the label is the plain "AI voice").
        $sharer = $harmony->user;
        $named = $sharer instanceof User && ! Gallery::hidden($sharer) && ! TextFilter::hasLink($sharer->name) && ! TextFilter::holds($sharer->name) ? $sharer->name : null;
        $own = array_map(static fn (string $part): array => ['name' => null, 'part' => $part, 'ai' => true, 'aiBasedOn' => $named, 'ownVoice' => true], array_values(array_filter((array) $harmony->own_voice_parts, 'is_string')));
        // A friend's parts (choir together): their first name only if they chose to show it, else the page's "a friend".
        $own = [...$own, ...array_map(static fn (array $f): array => ['name' => null, 'part' => (string) ($f['part'] ?? ''), 'ai' => true, 'aiBasedOn' => is_string($f['name'] ?? null) && $f['name'] !== '' ? $f['name'] : null, 'ownVoice' => true, 'friend' => true], array_values(array_filter((array) $harmony->own_voice_friends, 'is_array')))];

        if ($harmony->template_version_id === null) {
            return $own;
        }

        $parts = $this->partNames($harmony);
        // Its catalogue AI parts as kept on the share (a guide retired since still sang in the mix); the name of the voice's
        // singer only while they still credit it (withdrawn: the plain "AI voice").
        $snapshot = array_values(array_filter((array) $harmony->ai_parts, 'is_array'));
        $voices = AiVoice::query()->whereKey(array_filter(array_column($snapshot, 'voice')))->get()->keyBy('id');
        $ai = array_map(static fn (array $p): array => ['name' => null, 'part' => $parts[(string) ($p['part'] ?? '')] ?? (string) ($p['part'] ?? ''), 'ai' => true, 'aiBasedOn' => isset($p['voice']) ? $voices->get((int) $p['voice'])?->labelName() : null, 'ownVoice' => false], $snapshot);

        if ($harmony->guide_ids === []) {
            return [...$ai, ...$own];
        }

        return [...array_values(TemplateGuide::query()
            ->with(['submission.assignment.singer', 'creditUser'])
            ->whereKey($harmony->guide_ids)
            ->where('template_version_id', $harmony->template_version_id)
            ->orderBy('id')
            ->get()
            ->map(fn (TemplateGuide $guide): ?array => (($name = $guide->submission !== null ? $guide->submission->assignment->singer->credit() : ($guide->creditUser !== null ? GalleryViews::person($guide->creditUser)['name'] : null)) !== null ? ['name' => $name, 'part' => $parts[$guide->part_key] ?? $guide->part_key, 'ai' => false, 'aiBasedOn' => null, 'ownVoice' => false] : null))
            ->filter()
            ->all()), ...$ai, ...$own];
    }

    /**
     * Their own real voice in it (2026-11-23): the lead ("The lead is {name}'s own voice…"); null when it holds none, or
     * their name can't be shown (the AI line says it then).
     *
     * @param  list<array{name: string|null, part: string, ai: bool, aiBasedOn: string|null, ownVoice: bool, friend?: bool}>  $guides
     * @return array{kind: string, name: string}|null
     */
    public function realVoice(Harmony $harmony, array $guides): ?array
    {
        $real = (array) ($harmony->real_voice ?? []);
        $sharer = $harmony->user;
        $named = $sharer instanceof User && ! Gallery::hidden($sharer) && ! TextFilter::hasLink($sharer->name) && ! TextFilter::holds($sharer->name) ? $sharer->name : null;

        if ($named === null || $real === []) {
            return null;
        }

        // Only as the lead beside AI parts: their recording alone is never put on a public page (the coordinator,
        // 2026-11-23: "Your recording" shares the full choir).
        return isset($real['take']) && collect($guides)->contains('ai', true) ? ['kind' => 'lead', 'name' => $named] : null;
    }

    /**
     * The song, for a project made from a template; null otherwise.
     *
     * @return array{slug: string, title: string, credits: string, arrangedBy: string}|null
     */
    public function song(Harmony $harmony): ?array
    {
        $version = $harmony->templateVersion;

        if ($version === null) {
            return null;
        }

        $data = $version->data;
        $credits = (array) ($data['credits'] ?? []);
        $lines = [];
        foreach (['words', 'tune'] as $kind) {
            if (is_string($credits[$kind] ?? null) && $credits[$kind] !== '') {
                $lines[] = __("sharing.credits.{$kind}", ['name' => $credits[$kind]]);
            }
        }

        return [
            'slug' => $version->template->slug,
            'title' => (string) ($data['title'] ?? $version->template->title),
            'credits' => implode('. ', $lines),
            'arrangedBy' => 'Stackapella',
        ];
    }

    /**
     * The part's name, for "just my part"; null for the whole harmony.
     */
    public function part(Harmony $harmony): ?string
    {
        return $harmony->part_name;
    }

    /**
     * The line under the singers on the card and the video: the song, or the part and the song.
     */
    public function songLine(Harmony $harmony): string
    {
        $song = $this->song($harmony);

        if ($song === null) {
            return '';
        }

        $part = $this->part($harmony);

        return $part !== null ? __('sharing.just_part', ['part' => $part, 'song' => $song['title']]) : __('sharing.song_line', ['song' => $song['title']]);
    }

    public function description(Harmony $harmony): string
    {
        $song = $this->song($harmony);
        $part = $this->part($harmony);

        $played = $this->played($harmony);
        $names = $this->names($harmony);

        return match (true) {
            $song === null => __(['voice' => 'sharing.description_own', 'instrument' => 'sharing.description_own_played', 'both' => 'sharing.description_own_both'][$played], ['names' => $names]),
            // One part is one kind: an instrument's is played.
            $part !== null => __($played === 'instrument' ? 'sharing.description_part_played' : 'sharing.description_part', ['names' => $names, 'part' => $part, 'song' => $song['title']]),
            default => __(['voice' => 'sharing.description', 'instrument' => 'sharing.description_played', 'both' => 'sharing.description_both'][$played], ['names' => $names, 'song' => $song['title']]),
        };
    }

    /**
     * @return array<string, string>
     */
    private function partNames(Harmony $harmony): array
    {
        $names = [];
        foreach ((array) ($harmony->templateVersion->data['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null)) {
                $names[$part['key']] = (string) ($part['name'] ?? $part['key']);
            }
        }

        return $names;
    }

    /**
     * The harmony as its public pages show it (contract/api.md, "/h/<id>": `$harmony`), for
     * /h/<id> and the embedded player.
     *
     * @return array<string, mixed>
     */
    public function page(Harmony $harmony): array
    {
        $media = Storage::disk('media');

        return [
            'id' => $harmony->public_id,
            'title' => $this->title($harmony),
            'singers' => $this->singers($harmony),
            'guides' => $guides = $this->guides($harmony),
            'realVoice' => $this->realVoice($harmony, $guides),
            'song' => $this->song($harmony),
            'part' => $this->part($harmony),
            'durationSeconds' => $harmony->durationSeconds(),
            'audioUrl' => $media->url((string) $harmony->media_audio_key),
            'audioType' => 'audio/mp4',
            'peaks' => $harmony->peaks ?? [],
            'colors' => array_column($harmony->tracks, 'color'),
            // "Sung by", "Played by" or "Sung and played by", with the names (for the page's credit).
            'creditLine' => $this->singersLine($harmony),
            // One waveform per part, in the studio's order (the share's own tracks).
            'parts' => array_values(array_map(static fn (array $track, int $i): array => ['index' => $i + 1, 'name' => (string) $track['name'], 'color' => (string) $track['color'], 'kind' => ($track['kind'] ?? 'voice') === 'instrument' ? 'instrument' : 'voice', 'peaks' => self::partPeaks((string) ($track['peaks'] ?? ''))], $harmony->tracks, array_keys($harmony->tracks))),
            'cardUrl' => $media->url((string) $harmony->media_card_key),
            'pageUrl' => Hosts::public('h/'.$harmony->public_id),
            'createdAt' => $harmony->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /** How many points each part's waveform has on the page. */
    public const PART_PEAKS = 120;

    /**
     * A part's waveform for the page: its peaks (one byte per 50 ms, as the app sent them) down to
     * 120 points, the loudest in each, from 0 to 1.
     *
     * @return list<float>
     */
    public static function partPeaks(string $base64): array
    {
        $bytes = array_values((array) unpack('C*', (string) base64_decode($base64, true)));
        $count = count($bytes);

        if ($count === 0) {
            return [];
        }

        $points = [];

        for ($i = 0; $i < self::PART_PEAKS; $i++) {
            $from = intdiv($i * $count, self::PART_PEAKS);
            $to = min($count, max($from + 1, intdiv(($i + 1) * $count, self::PART_PEAKS)));
            $loudest = 0;

            for ($j = $from; $j < $to; $j++) {
                $loudest = max($loudest, (int) $bytes[$j]);
            }

            $points[] = round($loudest / 255, 3);
        }

        return $points;
    }

    /**
     * A live harmony with its media, by its public id, or null.
     */
    public static function live(string $publicId): ?Harmony
    {
        $found = Harmony::query()->with('user', 'templateVersion.template')->where('public_id', $publicId)->first();

        return $found !== null && $found->isLive() && $found->media_audio_key !== null ? $found : null;
    }
}
