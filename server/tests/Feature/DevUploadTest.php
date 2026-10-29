<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Storage\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The local stand-in for a presigned PUT behaves like Spaces: exact key, type and size.
 */
final class DevUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_put_stores_exactly_what_it_was_signed_for(): void
    {
        $upload = app(Uploads::class)->presign('takes/abc.wav', 'audio/wav', 4);

        $this->assertSame('PUT', $upload['method']);
        $this->assertSame(['Content-Type' => 'audio/wav', 'Content-Length' => '4'], $upload['headers']);

        $this->call('PUT', $upload['url'], [], [], [], ['CONTENT_TYPE' => 'audio/wav'], 'RIFF')->assertOk();
        Storage::disk('private')->assertExists('takes/abc.wav');
    }

    public function test_the_put_needs_no_csrf_token_or_session_like_spaces(): void
    {
        // Laravel's tests skip CSRF unless the app thinks it's running for real.
        $this->app->instance('env', 'local');
        $upload = app(Uploads::class)->presign('takes/abc.wav', 'audio/wav', 4);

        $response = $this->call('PUT', $upload['url'], [], [], [], ['CONTENT_TYPE' => 'audio/wav'], 'RIFF');

        $response->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_a_bigger_or_different_file_is_refused(): void
    {
        $upload = app(Uploads::class)->presign('takes/abc.wav', 'audio/wav', 4);

        $this->call('PUT', $upload['url'], [], [], [], ['CONTENT_TYPE' => 'audio/wav'], 'RIFF+more')->assertForbidden();
        $this->call('PUT', $upload['url'], [], [], [], ['CONTENT_TYPE' => 'text/html'], 'RIFF')->assertForbidden();
        Storage::disk('private')->assertMissing('takes/abc.wav');
    }

    public function test_an_unsigned_or_tampered_url_is_refused(): void
    {
        $upload = app(Uploads::class)->presign('takes/abc.wav', 'audio/wav', 4);

        $this->call('PUT', str_replace('bytes=4', 'bytes=9', $upload['url']), [], [], [], ['CONTENT_TYPE' => 'audio/wav'], 'RIFF')->assertForbidden();
        $this->call('PUT', '/dev/upload/takes/abc.wav', [], [], [], ['CONTENT_TYPE' => 'audio/wav'], 'RIFF')->assertForbidden();
    }
}
