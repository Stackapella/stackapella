<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Jobs\PostToSocial;
use App\Jobs\RemoveSocialPost;
use App\Models\AuditLog;
use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Models\SocialPost;
use App\Models\Template;
use App\Models\User;
use App\Services\Sharing\ShareVideoRenderer;
use App\Support\Gallery\Gallery;
use App\Support\Http\Hosts;
use App\Support\Sharing\HarmonyFacts;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Our own posts on X and Bluesky (docs/backend-plan.md D7; a port of iaw's SocialPoster): a new
 * song, the song of the month, or a harmony its singer said may be featured. Each is proposed
 * once per network (only networks with credentials), waits for Hillel's approval in admin, then
 * goes out after a 120-second undo; what it says is made, and checked, again just before. X posts
 * carry the image but no link; Bluesky posts carry both. Posts go out only in production.
 */
final class SocialPoster
{
    public const UNDO_SECONDS = 120;

    /** What a post about a harmony reads (its facts), loaded with it. */
    public const HARMONY = ['user', 'templateVersion.template'];

    /**
     * Posts with what they're about, in a few queries for a list.
     *
     * @return array<string, Closure>
     */
    public static function with(): array
    {
        return ['postable' => fn (MorphTo $morph) => $morph->morphWith([Template::class => ['currentVersion'], Harmony::class => self::HARMONY])];
    }

    public function __construct(private readonly XClient $x, private readonly BlueskyClient $bluesky, private readonly HarmonyFacts $facts) {}

    /**
     * @return array<string, SocialClient>
     */
    public function networks(): array
    {
        return array_filter(['x' => $this->x, 'bluesky' => $this->bluesky], static fn (SocialClient $client): bool => $client->configured());
    }

    public function client(string $network): SocialClient
    {
        return $network === 'x' ? $this->x : $this->bluesky;
    }

    /**
     * Propose posts about something (one per network), to wait for Hillel. Answers how many are new.
     */
    public function propose(Template|Harmony $item, string $occasion): int
    {
        // Our posts are stackapella.com's own: another install of the app proposes none. Before
        // launch nobody outside can open what a post would point at, and every song is new at
        // launch, so nothing is proposed then.
        if (config('app.is_nexus') !== true || config('app.coming_soon') === true) {
            return 0;
        }

        $made = 0;

        foreach (array_keys($this->networks()) as $network) {
            $post = SocialPost::query()->firstOrCreate(
                ['postable_type' => $item->getMorphClass(), 'postable_id' => $item->getKey(), 'occasion' => $occasion, 'network' => $network],
                ['status' => SocialPost::AWAITING],
            );
            $made += $post->wasRecentlyCreated ? 1 : 0;
        }

        return $made;
    }

    public function approve(SocialPost $post, User $admin): SocialPost
    {
        $claimed = SocialPost::query()->whereKey($post->id)->where('status', SocialPost::AWAITING)
            ->update(['status' => SocialPost::QUEUED, 'approved_by' => $admin->id, 'approved_at' => now(), 'scheduled_for' => now()->addSeconds(self::UNDO_SECONDS), 'updated_at' => now()]);

        if ($claimed === 1) {
            PostToSocial::dispatch($post->id)->delay(now()->addSeconds(self::UNDO_SECONDS));
        }

        return $post->refresh();
    }

    /**
     * Not sent: turned down, or undone within the window.
     */
    public function cancel(SocialPost $post, ?User $admin = null, ?string $ip = null): SocialPost
    {
        if (SocialPost::query()->whereKey($post->id)->whereIn('status', [SocialPost::AWAITING, SocialPost::QUEUED])->update(['status' => SocialPost::CANCELLED, 'updated_at' => now()]) === 1 && $admin !== null) {
            AuditLog::record('social.cancelled', null, $admin, after: ['post' => $post->id, 'network' => $post->network], ip: $ip);
        }

        return $post->refresh();
    }

    public function retry(SocialPost $post, ?User $admin = null, ?string $ip = null): SocialPost
    {
        if (SocialPost::query()->whereKey($post->id)->where('status', SocialPost::FAILED)->update(['status' => SocialPost::QUEUED, 'error' => null, 'scheduled_for' => now(), 'updated_at' => now()]) === 1) {
            AuditLog::record('social.retried', null, $admin, after: ['post' => $post->id, 'network' => $post->network], ip: $ip);
            PostToSocial::dispatch($post->id);
        }

        return $post->refresh();
    }

    /** A waiting post older than this is stale: whatever it was about is no longer news. */
    public const STALE_DAYS = 14;

    /**
     * Waiting posts that can't go any more, cancelled with the reason, so Hillel's list holds only
     * what could still be posted: anything before launch, anything stale, a song or harmony that
     * changed (taken down, its month over, no longer public). Answers how many went.
     */
    public function tidy(): int
    {
        $cancelled = 0;

        foreach (SocialPost::query()->with(self::with())->where('status', SocialPost::AWAITING)->get() as $post) {
            $why = match (true) {
                config('app.coming_soon') === true => 'Before launch: nothing is posted until the app is open to everyone.',
                $post->created_at !== null && $post->created_at->lt(now()->subDays(self::STALE_DAYS)) => 'Stale: waited more than '.self::STALE_DAYS.' days.',
                $this->message($post, false) === null => 'Nothing to say any more: it changed or was taken down.',
                default => null,
            };

            if ($why !== null && SocialPost::query()->whereKey($post->id)->where('status', SocialPost::AWAITING)->update(['status' => SocialPost::CANCELLED, 'error' => $why, 'updated_at' => now()]) === 1) {
                $cancelled++;
            }
        }

        return $cancelled;
    }

    /**
     * Posts about a harmony its singer agreed to, proposed (once per network) for Hillel.
     *
     * @return Collection<int, SocialPost>
     */
    public function feature(Harmony $harmony): Collection
    {
        $this->propose($harmony, SocialPost::FEATURED);

        return SocialPost::query()->with(self::with())->where('postable_type', $harmony->getMorphClass())->where('postable_id', $harmony->id)->where('occasion', SocialPost::FEATURED)->orderBy('id')->get();
    }

    /**
     * The owner said no (or deleted it, or their account): nothing about it that hasn't gone yet
     * goes, and what has gone is taken down from the network.
     */
    public static function withdraw(Harmony $harmony): void
    {
        $posts = SocialPost::query()->where('postable_type', $harmony->getMorphClass())->where('postable_id', $harmony->id);
        (clone $posts)->whereIn('status', [SocialPost::AWAITING, SocialPost::QUEUED])
            ->update(['status' => SocialPost::CANCELLED, 'error' => 'Withdrawn by the singer.', 'updated_at' => now()]);

        foreach ((clone $posts)->where('status', SocialPost::POSTED)->whereNotNull('remote_id')->pluck('id') as $id) {
            RemoveSocialPost::dispatch((int) $id)->afterCommit();
        }
    }

    /**
     * What the post says, made now from the item as it is, or null when it mustn't go any more
     * (a song taken down, a month over, a harmony hidden or no longer agreed to). Without media
     * (admin's list, an approval), the card and video aren't read: only the posting job needs them.
     */
    public function message(SocialPost $post, bool $media = true): ?SocialMessage
    {
        $item = $post->postable;
        $x = $post->network === 'x';

        if ($item instanceof Template) {
            $version = $item->loadMissing('currentVersion')->currentVersion;

            if ($item->owner_id !== null || $item->deleted_at !== null || $version === null || ! $version->reviewed) {
                return null;
            }

            $month = str_starts_with($post->occasion, SocialPost::MONTH);

            if ($month && ! $item->isFreeOn(now())) {
                return null;
            }

            $parts = count((array) ($version->data['parts'] ?? []));
            $url = rtrim((string) config('app.url'), '/').'/?'.http_build_query(['song' => $item->slug, 'ref' => 'social']);
            $text = $month
                ? __('social.month', ['title' => $item->title, 'parts' => $parts, 'until' => $item->free_until?->format('j F') ?? ''])
                : __('social.new_song', ['title' => $item->title, 'parts' => $parts]);

            return new SocialMessage(self::fit($text.($x ? '' : "\n\n".$url), $x), $url, $item->title, __('social.song_card', ['parts' => $parts]));
        }

        if ($item instanceof Harmony) {
            $owner = $item->loadMissing(self::HARMONY)->user;

            // Featured only while it's public by the gallery's own rule (live, 18 or over, not
            // suspended, every friend on it agreed) and its singer said yes to featuring.
            if ($item->may_feature_at === null || $owner === null || ! app(Gallery::class)->isPublic($item)) {
                return null;
            }

            $title = $this->facts->title($item);
            $url = Hosts::public('h/'.$item->public_id);
            // Credited by name, and by their own handle on that network when they gave one (never
            // their Stackapella handle with an @: on X that would mention whoever owns it there).
            $theirs = $x ? $owner->x_handle : $owner->bluesky_handle;
            // A hidden profile (Hide profile) is never named: "a Stackapella singer".
            $by = Gallery::hidden($owner) ? __('sharing.a_singer') : $owner->name.(filled($theirs) ? ' (@'.ltrim((string) $theirs, '@').')' : '');
            $card = $media && $item->media_card_key !== null && Storage::disk('media')->exists($item->media_card_key) ? Storage::disk('media')->get($item->media_card_key) : null;
            $video = $media ? self::video($item) : null;

            return new SocialMessage(self::fit(__(['voice' => 'social.featured', 'instrument' => 'social.featured_played', 'both' => 'social.featured_both'][$this->facts->played($item)], ['title' => $title, 'by' => $by]).($x ? '' : "\n\n".$url), $x), $url, $title, $this->facts->description($item), $card, $this->facts->imageAlt($item), $video, $video !== null ? ShareVideoRenderer::WIDTH : 0, $video !== null ? ShareVideoRenderer::HEIGHT : 0);
        }

        return null;
    }

    /**
     * The harmony's own share video, the newest its singer made and kept (they chose it to share;
     * featuring it is their yes to mayFeature), while our posts may carry video at all.
     */
    private static function video(Harmony $harmony): ?string
    {
        // The video shows its title: one held for review isn't shown (the card says "Untitled").
        if (config('services.social.videos') !== true || $harmony->title_held_at !== null) {
            return null;
        }

        $made = ShareVideo::query()->where('harmony_id', $harmony->id)->where('user_id', $harmony->user_id)->where('status', ShareVideo::READY)
            ->whereNotNull('file_key')->where(fn ($kept) => $kept->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('id')->first();

        // At most 100 MB: Bluesky's limit (a 30-second share video is a few MB).
        if ($made === null || ! Storage::disk('private')->exists((string) $made->file_key) || (int) $made->bytes > 100 * 1024 * 1024) {
            return null;
        }

        return Storage::disk('private')->get((string) $made->file_key);
    }

    /**
     * A post as admin shows it (contract/schemas/admin.social.post.json).
     *
     * @return array<string, mixed>
     */
    public function view(SocialPost $post): array
    {
        $message = in_array($post->status, [SocialPost::AWAITING, SocialPost::QUEUED, SocialPost::FAILED], true) ? $this->message($post, media: false) : null;
        $item = $post->postable;

        return [
            'id' => $post->sqid(),
            'network' => $post->network,
            'occasion' => str_starts_with($post->occasion, SocialPost::MONTH) ? SocialPost::MONTH : $post->occasion,
            'status' => $post->status,
            'about' => match (true) {
                $item instanceof Template => ['kind' => 'song', 'id' => $item->slug, 'title' => $item->title],
                $item instanceof Harmony => ['kind' => 'harmony', 'id' => (string) $item->public_id, 'title' => $this->facts->title($item)],
                default => ['kind' => 'gone', 'id' => '', 'title' => ''],
            },
            'text' => $message?->text,
            'url' => $post->url,
            'error' => $post->error,
            'scheduledFor' => $post->scheduled_for?->utc()->toIso8601ZuluString(),
            'postedAt' => $post->posted_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $post->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    private static function fit(string $text, bool $x): string
    {
        $limit = $x ? 280 : 300;

        if (mb_strlen($text) < $limit) {
            return $text;
        }

        $text = mb_substr($text, 0, $limit - 5);

        return mb_substr($text, 0, (int) mb_strrpos($text, ' ')).'...';
    }
}
