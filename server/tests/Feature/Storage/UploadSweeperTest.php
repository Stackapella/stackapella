<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\Template;
use App\Models\UploadLink;
use App\Support\Storage\Uploads;
use App\Support\Storage\UploadSweeper;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemAdapter;
use League\Flysystem\Filesystem;
use Tests\Support\BetaFixtures;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The abuse review's upload blocker (2026-11-21): a presigned PUT can't bind its size, so links are short and kept,
 * anything bigger than declared is deleted at once with every version, every version at a done link's key goes, and
 * every presigned upload counts toward the day's bytes.
 */
final class UploadSweeperTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;
    use Sharing;

    public function test_a_link_opens_for_three_minutes_and_is_kept(): void
    {
        $upload = app(Uploads::class)->presign('uploads/a.wav', 'audio/wav', 44);

        $this->assertEqualsWithDelta(now()->addMinutes(3)->getTimestamp(), $upload['expiresAt']->getTimestamp(), 2);
        $link = UploadLink::query()->where('upload_key', 'uploads/a.wav')->firstOrFail();
        $this->assertSame(44, $link->bytes);

        // Asked for again (a retry): one link, its expiry moved.
        $this->travel(1)->minutes();
        app(Uploads::class)->presign('uploads/a.wav', 'audio/wav', 44);
        $this->assertSame(1, UploadLink::query()->count());
        $this->assertEqualsWithDelta(now()->addMinutes(3)->getTimestamp(), UploadLink::query()->firstOrFail()->expires_at->getTimestamp(), 2);
    }

    public function test_bigger_than_declared_goes_at_once_and_a_done_link_takes_what_is_left(): void
    {
        $disk = Storage::disk('private');
        UploadLink::factory()->create(['upload_key' => 'uploads/big.wav', 'bytes' => 10]);
        $disk->put('uploads/big.wav', str_repeat('x', 11));
        UploadLink::factory()->create(['upload_key' => 'uploads/exact.wav', 'bytes' => 10]);
        $disk->put('uploads/exact.wav', str_repeat('x', 10));
        UploadLink::factory()->create(['upload_key' => 'uploads/done.wav', 'bytes' => 10, 'expires_at' => now()->subMinutes(61)]);
        $disk->put('uploads/done.wav', str_repeat('x', 10));
        // Waiting for its complete: kept, though its link has stopped.
        UploadLink::factory()->create(['upload_key' => 'uploads/waiting.wav', 'bytes' => 10, 'expires_at' => now()->subMinutes(30)]);
        $disk->put('uploads/waiting.wav', str_repeat('x', 10));

        $this->artisan('app:uploads:sweep')->expectsOutput('Deleted 1 oversized uploads and swept 1 done links.')->assertSuccessful();

        $disk->assertMissing('uploads/big.wav');
        $disk->assertExists('uploads/exact.wav');
        $disk->assertMissing('uploads/done.wav');
        $disk->assertExists('uploads/waiting.wav');
        $this->assertSame(['uploads/big.wav', 'uploads/exact.wav', 'uploads/waiting.wav'], UploadLink::query()->orderBy('upload_key')->pluck('upload_key')->all());
    }

    public function test_every_version_at_the_key_goes_and_only_that_keys(): void
    {
        $calls = [];
        $mock = new MockHandler;
        $mock->append(function (CommandInterface $command) use (&$calls): Result {
            $calls[] = [$command->getName(), $command['Prefix'] ?? null];

            return new Result([
                'Versions' => [['Key' => 'uploads/a.wav', 'VersionId' => 'v1'], ['Key' => 'uploads/a.wav', 'VersionId' => 'v2'], ['Key' => 'uploads/a.wav.old', 'VersionId' => 'v9']],
                'DeleteMarkers' => [['Key' => 'uploads/a.wav', 'VersionId' => 'm1']],
            ]);
        });
        foreach (range(1, 3) as $ignored) {
            $mock->append(function (CommandInterface $command) use (&$calls): Result {
                $calls[] = [$command->getName(), $command['Key'].'@'.$command['VersionId']];

                return new Result([]);
            });
        }
        $this->fakeSpaces($mock);

        app(UploadSweeper::class)->purge('uploads/a.wav');

        $this->assertSame([['ListObjectVersions', 'uploads/a.wav'], ['DeleteObject', 'uploads/a.wav@v1'], ['DeleteObject', 'uploads/a.wav@v2'], ['DeleteObject', 'uploads/a.wav@m1']], $calls);
    }

    public function test_a_key_that_may_not_list_versions_still_deletes_the_object(): void
    {
        $calls = [];
        $mock = new MockHandler;
        $mock->append(fn (CommandInterface $command): S3Exception => new S3Exception('AccessDenied', $command, ['code' => 'AccessDenied']));
        $mock->append(function (CommandInterface $command) use (&$calls): Result {
            $calls[] = [$command->getName(), $command['Key']];

            return new Result([]);
        });
        $this->fakeSpaces($mock);

        app(UploadSweeper::class)->purge('uploads/a.wav');

        $this->assertSame([['DeleteObject', 'uploads/a.wav']], $calls);
    }

    public function test_a_shared_song_and_a_choir_clip_count_toward_the_days_bytes(): void
    {
        $this->catalogue();
        $user = $this->owner();
        [$project] = $this->projectWithPart();
        $version = (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version, 'status' => OwnChoirStatus::Ready]);
        $bass = OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'part_key' => 'bass', 'frames' => 48000 * 3]);
        config(['limits.upload-bytes.free_mb.max' => 0, 'limits.upload-bytes.plus_mb.max' => 0]);

        $this->startHarmony($project, Wav::stereo(2.0), ['choirParts' => [$bass->sqid()]])->assertStatus(429)->assertJsonPath('code', 'rate_limited');
    }

    private function fakeSpaces(MockHandler $mock): void
    {
        $client = new S3Client(['version' => 'latest', 'region' => 'nyc3', 'endpoint' => 'https://spaces.example.com', 'credentials' => ['key' => 'k', 'secret' => 's'], 'handler' => $mock]);
        $adapter = new FlysystemAdapter($client, 'bucket');
        Storage::set('private', new AwsS3V3Adapter(new Filesystem($adapter), $adapter, ['bucket' => 'bucket'], $client));
        config(['filesystems.disks.private.bucket' => 'bucket']);
    }
}
