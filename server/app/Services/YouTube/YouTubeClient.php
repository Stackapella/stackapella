<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Exceptions\Refusal;
use Illuminate\Support\Facades\Http;

/**
 * The YouTube Data API v3, server side only (docs/youtube-sync-plan.md): the key never reaches a browser or the repo
 * (Developer Policies III.D.1.d). videos.list checks up to 50 videos for 1 unit of the 10,000 a day; search.list has its
 * own allowance of 100 calls a day, so only the sync tool searches, never singers. Nothing it returns is stored beyond
 * the 30 days the policies allow (SongVideo::KEEP_DAYS).
 */
final class YouTubeClient
{
    private const API = 'https://www.googleapis.com/youtube/v3/';

    /** The most ids one videos.list call takes. */
    public const BATCH = 50;

    public function configured(): bool
    {
        return (string) config('services.youtube.key') !== '';
    }

    /**
     * The videos YouTube still has, by id: a missing id was removed (or never existed).
     *
     * @param  list<string>  $ids
     * @return array<string, YouTubeVideo>
     */
    public function videos(array $ids): array
    {
        $found = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::BATCH) as $chunk) {
            $items = $this->get('videos', ['part' => 'snippet,status,contentDetails', 'id' => implode(',', $chunk), 'maxResults' => self::BATCH])['items'] ?? [];
            foreach (is_array($items) ? $items : [] as $item) {
                if (is_array($item) && is_string($item['id'] ?? null)) {
                    $found[$item['id']] = $this->video($item);
                }
            }
        }

        return $found;
    }

    /**
     * Embeddable videos for a search, best first, each checked (one search call and one videos.list call).
     *
     * @return list<YouTubeVideo>
     */
    public function search(string $query): array
    {
        $items = $this->get('search', ['part' => 'snippet', 'type' => 'video', 'videoEmbeddable' => 'true', 'safeSearch' => 'strict', 'maxResults' => 15, 'q' => $query])['items'] ?? [];
        $ids = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $id = is_array($item) && is_array($item['id'] ?? null) ? ($item['id']['videoId'] ?? null) : null;
            if (is_string($id)) {
                $ids[] = $id;
            }
        }
        $videos = $this->videos($ids);

        return array_values(array_filter(array_map(static fn (string $id): ?YouTubeVideo => $videos[$id] ?? null, $ids)));
    }

    /**
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>
     */
    private function get(string $method, array $query): array
    {
        if (! $this->configured()) {
            throw new Refusal(409, 'youtube_unavailable');
        }
        $response = Http::withUserAgent('Stackapella/1.0')->acceptJson()->timeout(20)
            ->get(self::API.$method, [...$query, 'key' => (string) config('services.youtube.key')]);
        if (! $response->successful()) {
            report(new \RuntimeException("YouTube {$method} answered {$response->status()}"));
            throw new Refusal(409, 'youtube_unavailable');
        }
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<mixed>  $item
     */
    private function video(array $item): YouTubeVideo
    {
        $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
        $status = is_array($item['status'] ?? null) ? $item['status'] : [];
        $details = is_array($item['contentDetails'] ?? null) ? $item['contentDetails'] : [];
        $rating = is_array($details['contentRating'] ?? null) ? $details['contentRating'] : [];

        return new YouTubeVideo(
            id: (string) $item['id'],
            title: mb_substr((string) ($snippet['title'] ?? ''), 0, 200),
            channelTitle: mb_substr((string) ($snippet['channelTitle'] ?? ''), 0, 200),
            durationSeconds: self::seconds((string) ($details['duration'] ?? '')),
            embeddable: ($status['embeddable'] ?? false) === true,
            privacy: (string) ($status['privacyStatus'] ?? 'private'),
            madeForKids: ($status['madeForKids'] ?? false) === true,
            ageRestricted: ($rating['ytRating'] ?? null) === 'ytAgeRestricted',
        );
    }

    /** ISO 8601's PT#H#M#S (YouTube's durations) in seconds; 0 when it isn't one (a live stream says P0D). */
    public static function seconds(string $duration): int
    {
        if (preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $m) !== 1) {
            return 0;
        }

        return (int) ($m[1] ?? 0) * 86400 + (int) ($m[2] ?? 0) * 3600 + (int) ($m[3] ?? 0) * 60 + (int) ($m[4] ?? 0);
    }
}
