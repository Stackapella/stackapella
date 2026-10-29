<?php

declare(strict_types=1);

namespace App\Services\Social;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bluesky (AT Protocol; a port of iaw's BlueskyClient): a session from our app password (reused,
 * dropped when a post is refused), the card uploaded as a blob, and a post with link and hashtag
 * facets plus a link card.
 */
final class BlueskyClient implements SocialClient
{
    private const SESSION = 'bluesky:session';

    public function configured(): bool
    {
        return filled(config('services.bluesky.handle')) && filled(config('services.bluesky.app_password'));
    }

    public function post(SocialMessage $message): array
    {
        $session = $this->session();
        $service = rtrim((string) config('services.bluesky.service'), '/');
        $external = ['uri' => $message->url, 'title' => Str::limit($message->title, 290), 'description' => Str::limit($message->description, 290)];

        $embed = ['$type' => 'app.bsky.embed.external', 'external' => $external];

        if ($message->video !== null) {
            // The video itself (one embed per post, so the link stays in the text, with its facet).
            $blob = Http::withToken($session['accessJwt'])->withBody($message->video, 'video/mp4')->timeout(120)->post($service.'/xrpc/com.atproto.repo.uploadBlob');

            if ($blob->successful()) {
                $embed = ['$type' => 'app.bsky.embed.video', 'video' => $blob->json('blob'), 'alt' => Str::limit($message->imageAlt, 900), 'aspectRatio' => ['width' => $message->videoWidth, 'height' => $message->videoHeight]];
            } else {
                // Refused: the post goes out with its card instead.
                report(new RuntimeException('Bluesky refused the video ('.$blob->status().'); posting the card.'));
            }
        }

        if ($embed['$type'] === 'app.bsky.embed.external' && $message->image !== null) {
            $blob = Http::withToken($session['accessJwt'])->withBody($message->image, 'image/jpeg')->timeout(30)->post($service.'/xrpc/com.atproto.repo.uploadBlob');

            if ($blob->successful()) {
                $embed['external']['thumb'] = $blob->json('blob');
            }
        }

        $response = Http::withToken($session['accessJwt'])->timeout(20)->post($service.'/xrpc/com.atproto.repo.createRecord', [
            'repo' => $session['did'],
            'collection' => 'app.bsky.feed.post',
            'record' => [
                '$type' => 'app.bsky.feed.post',
                'text' => $message->text,
                'createdAt' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'facets' => self::facets($message->text),
                'langs' => ['en'],
                'embed' => $embed,
            ],
        ]);

        if (! $response->successful()) {
            Cache::forget(self::SESSION);

            throw new RuntimeException('Bluesky refused the post ('.$response->status().'): '.Str::limit($response->body(), 300));
        }

        $uri = (string) $response->json('uri');

        return ['id' => $uri, 'url' => 'https://bsky.app/profile/'.config('services.bluesky.handle').'/post/'.Str::afterLast($uri, '/')];
    }

    public function delete(string $id): void
    {
        $session = $this->session();
        $response = Http::withToken($session['accessJwt'])->timeout(20)->post(rtrim((string) config('services.bluesky.service'), '/').'/xrpc/com.atproto.repo.deleteRecord', [
            'repo' => $session['did'],
            'collection' => 'app.bsky.feed.post',
            'rkey' => Str::afterLast($id, '/'),
        ]);

        if (! $response->successful()) {
            Cache::forget(self::SESSION);

            throw new RuntimeException('Bluesky refused to delete the post ('.$response->status().'): '.Str::limit($response->body(), 300));
        }
    }

    /**
     * Rich-text facets: UTF-8 byte offsets for every link and hashtag in the text.
     *
     * @return list<array<string, mixed>>
     */
    public static function facets(string $text): array
    {
        $facets = [];

        preg_match_all('~https?://[^\s]+~u', $text, $links, PREG_OFFSET_CAPTURE);
        foreach ($links[0] as [$link, $offset]) {
            $facets[] = ['index' => ['byteStart' => $offset, 'byteEnd' => $offset + strlen($link)], 'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $link]]];
        }

        preg_match_all('~(?<=^|\s)#([\p{L}\p{N}_]+)~u', $text, $tags, PREG_OFFSET_CAPTURE);
        foreach ($tags[0] as $index => [$tag, $offset]) {
            $facets[] = ['index' => ['byteStart' => $offset, 'byteEnd' => $offset + strlen($tag)], 'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $tags[1][$index][0]]]];
        }

        return $facets;
    }

    /**
     * @return array{accessJwt: string, did: string}
     */
    private function session(): array
    {
        /** @var array{accessJwt: string, did: string} */
        return Cache::remember(self::SESSION, now()->addMinutes(90), function (): array {
            $response = Http::timeout(15)->post(rtrim((string) config('services.bluesky.service'), '/').'/xrpc/com.atproto.server.createSession', [
                'identifier' => config('services.bluesky.handle'),
                'password' => config('services.bluesky.app_password'),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Bluesky sign-in failed ('.$response->status().').');
            }

            return ['accessJwt' => (string) $response->json('accessJwt'), 'did' => (string) $response->json('did')];
        });
    }
}
