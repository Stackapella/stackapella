<?php

declare(strict_types=1);

namespace App\Services\Social;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * X API v2 with our account's OAuth 1.0a user keys (a port of iaw's XClient): POST /2/tweets,
 * the image uploaded through the v2 media endpoint first, and never a link (X charges more for a
 * post with one).
 */
final class XClient implements SocialClient
{
    /** The longest X's video processing is waited for. */
    private const MOST_WAIT_SECONDS = 150;

    private const API = 'https://api.x.com/2';

    public function configured(): bool
    {
        $keys = (array) config('services.x');

        return filled($keys['api_key'] ?? null) && filled($keys['api_secret'] ?? null) && filled($keys['access_token'] ?? null) && filled($keys['access_secret'] ?? null);
    }

    public function post(SocialMessage $message): array
    {
        $body = ['text' => self::withoutLinks($message->text)];

        if ($message->video !== null) {
            $body['media'] = ['media_ids' => [$this->uploadVideo($message->video)]];
        } elseif ($message->image !== null) {
            $body['media'] = ['media_ids' => [$this->uploadImage($message->image)]];
        }

        $response = $this->client('POST', self::API.'/tweets')->post(self::API.'/tweets', $body);
        $id = $response->json('data.id');

        // X's pay-per-use account with no credits left answers 402 (or a 403 naming credits): said
        // plainly, and never tried again by itself (the post is `failed`, for Retry once topped up).
        if ($response->status() === 402 || ($response->status() === 403 && str_contains(strtolower($response->body()), 'credit'))) {
            throw new RuntimeException('X needs credits (its account is pay-per-use): top it up, then Retry.');
        }

        if (! $response->successful() || ! is_string($id)) {
            throw new RuntimeException('X refused the post ('.$response->status().'): '.Str::limit($response->body(), 300));
        }

        return ['id' => $id, 'url' => 'https://x.com/i/web/status/'.$id];
    }

    public function delete(string $id): void
    {
        $url = self::API.'/tweets/'.rawurlencode($id);
        $response = $this->client('DELETE', $url)->delete($url);

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('X refused to delete the post ('.$response->status().'): '.Str::limit($response->body(), 300));
        }
    }

    /**
     * No links: URLs are dropped, and a bare domain gets a one-dot leader so X can't link it.
     */
    public static function withoutLinks(string $text): string
    {
        $text = (string) preg_replace('~(?:https?://|www\.)\S+~iu', '', $text);
        $text = (string) preg_replace('/(?<=[\p{L}\p{N}_-])\.(?=\p{L}{2,}\b)/u', "\u{2024}", $text);
        $text = (string) preg_replace(['/[ \t]+/u', '/ *\n */u', '/\n{3,}/u'], [' ', "\n", "\n\n"], $text);

        return trim($text);
    }

    private function uploadImage(string $jpeg): string
    {
        $url = self::API.'/media/upload';
        $response = $this->client('POST', $url)->asMultipart()->attach('media', $jpeg, 'card.jpg', ['Content-Type' => 'image/jpeg'])->post($url, ['media_category' => 'tweet_image']);
        $id = $response->json('data.id') ?? $response->json('media_id_string');

        if (! $response->successful() || ! is_string($id)) {
            throw new RuntimeException('X refused the image ('.$response->status().').');
        }

        return $id;
    }

    /**
     * A video by X's v2 chunked upload: initialize, append in 4 MB chunks, finalize, then wait for
     * X to process it (as long as it asks, at most a few minutes) before the post can carry it.
     */
    private function uploadVideo(string $mp4): string
    {
        $init = self::API.'/media/upload/initialize';
        $started = $this->client('POST', $init)->post($init, ['media_type' => 'video/mp4', 'total_bytes' => strlen($mp4), 'media_category' => 'tweet_video']);
        $id = $started->json('data.id');

        if (! $started->successful() || ! is_string($id)) {
            throw new RuntimeException('X refused the video ('.$started->status().').');
        }

        foreach (str_split($mp4, 4 * 1024 * 1024) as $index => $chunk) {
            $append = self::API.'/media/upload/'.rawurlencode($id).'/append';
            $sent = $this->client('POST', $append)->asMultipart()->attach('media', $chunk, 'chunk.mp4', ['Content-Type' => 'application/octet-stream'])->post($append, ['segment_index' => (string) $index]);

            if (! $sent->successful()) {
                throw new RuntimeException('X refused part of the video ('.$sent->status().').');
            }
        }

        $finalize = self::API.'/media/upload/'.rawurlencode($id).'/finalize';
        $done = $this->client('POST', $finalize)->post($finalize);
        $state = $done->json('data.processing_info');

        // At most 150 seconds in all, under the job's timeout; a failed check is a failure, never
        // taken as done.
        for ($waited = 0; is_array($state) && in_array($state['state'] ?? null, ['pending', 'in_progress'], true);) {
            $wait = max(1, min(30, (int) ($state['check_after_secs'] ?? 5)));

            if ($waited + $wait > self::MOST_WAIT_SECONDS) {
                throw new RuntimeException('X took too long to process the video.');
            }

            Sleep::for($wait)->seconds();
            $waited += $wait;
            $status = self::API.'/media/upload?'.http_build_query(['command' => 'STATUS', 'media_id' => $id]);
            $checked = $this->client('GET', $status)->get($status);

            if (! $checked->successful()) {
                throw new RuntimeException('X didn\'t say whether the video was ready ('.$checked->status().').');
            }

            $state = $checked->json('data.processing_info');
        }

        if (! $done->successful() || (is_array($state) && ($state['state'] ?? null) !== 'succeeded')) {
            throw new RuntimeException('X couldn\'t process the video ('.(is_array($state) ? (string) ($state['state'] ?? '') : (string) $done->status()).').');
        }

        return $id;
    }

    /**
     * An OAuth 1.0a signed request: JSON and multipart bodies aren't signed, so only the oauth_*
     * parameters (and any query string) are.
     */
    private function client(string $method, string $url): PendingRequest
    {
        $keys = (array) config('services.x');
        $oauth = [
            'oauth_consumer_key' => (string) $keys['api_key'],
            'oauth_nonce' => Str::random(32),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => (string) $keys['access_token'],
            'oauth_version' => '1.0',
        ];
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException("Can't sign {$url}: it isn't an absolute URL.");
        }

        // Not parse_str(): it would turn the dot in a key like user.fields into an underscore.
        $query = [];
        foreach (array_filter(explode('&', (string) ($parts['query'] ?? ''))) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $query[rawurldecode($name)] = rawurldecode($value);
        }

        $params = [...$oauth, ...$query];
        ksort($params);
        $base = strtoupper($method).'&'.rawurlencode($parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '')).'&'.rawurlencode(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        $key = rawurlencode((string) $keys['api_secret']).'&'.rawurlencode((string) $keys['access_secret']);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));
        $header = 'OAuth '.implode(', ', array_map(static fn (string $k, string $v): string => $k.'="'.rawurlencode($v).'"', array_keys($oauth), $oauth));

        return Http::withHeaders(['Authorization' => $header])->timeout(20)->acceptJson();
    }
}
