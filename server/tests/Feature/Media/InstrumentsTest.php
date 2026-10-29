<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Support\Media\Instruments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * app:instruments:upload: a built instrument goes up once, at its own version, and the index
 * points at the current one.
 */
final class InstrumentsTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        $this->folder = sys_get_temp_dir().'/instrument-'.uniqid();
        File::makeDirectory($this->folder.'/samples', 0755, true);
        File::put($this->folder.'/samples/c4.mp3', 'mp3');
        $this->manifest(['path' => 'samples/c4.mp3', 'bytes' => 3, 'sha256' => hash('sha256', 'mp3')]);
    }

    /**
     * A manifest in contract/instruments.md's format 1: one zone for every key and velocity.
     *
     * @param  array<string, mixed>  $file
     * @param  array<string, mixed>  $with
     */
    private function manifest(array $file, string $name = 'piano', string $version = '1', array $with = []): void
    {
        File::put($this->folder.'/manifest.json', (string) json_encode([
            'format' => 1, 'name' => $name, 'version' => $version, 'title' => 'Grand piano', 'kind' => 'pitched', 'sampleRate' => 44100, 'channels' => 1, 'release' => 0.3,
            'zones' => [['file' => 'samples/c4.mp3', 'frames' => 44100, 'encoderDelay' => 1105, 'onset' => 0.003, 'velLo' => 1, 'velHi' => 127, 'root' => 60, 'lo' => 0, 'hi' => 127, 'tune' => 0]],
            'licence' => 'CC0-1.0', 'credit' => 'Test samples.', 'sources' => [], 'files' => [$file], ...$with,
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    public function test_an_instrument_goes_up_once_per_version_and_the_index_follows(): void
    {
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder])->expectsOutputToContain('Uploaded 2 files')->assertSuccessful();
        Storage::disk('media')->assertExists(['instruments/piano/1/manifest.json', 'instruments/piano/1/samples/c4.mp3']);
        $this->assertSame('1', Instruments::index()['instruments']['piano']['version']);

        // What's uploaded never changes: the same version again is refused.
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder])->expectsOutputToContain('already uploaded')->assertFailed();

        $file = ['path' => 'samples/c4.mp3', 'bytes' => 3, 'sha256' => hash('sha256', 'mp3')];
        $this->manifest($file, version: '1.1');
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1.1', 'folder' => $this->folder])->assertSuccessful();
        $this->manifest($file, 'guitar');
        $this->artisan('app:instruments:upload', ['name' => 'guitar', 'version' => '1', 'folder' => $this->folder, '--not-current' => true])->assertSuccessful();
        $index = json_decode((string) Storage::disk('media')->get(Instruments::INDEX), true);
        $this->assertSame([['piano', '1.1']], array_map(static fn (array $entry): array => [$entry['name'], $entry['version']], $index['instruments']));
        $this->assertStringEndsWith('instruments/piano/1.1/manifest.json', $index['instruments'][0]['manifest']);

        // Back to an earlier version, and only to one that's there.
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', '--current' => true])->assertSuccessful();
        $this->assertSame('1', Instruments::index()['instruments']['piano']['version']);
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '9', '--current' => true])->expectsOutputToContain("hasn't been uploaded")->assertFailed();
    }

    public function test_the_apps_find_the_index_in_auth_config_only_while_instruments_are_on(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('data.instrumentsIndexUrl', null);

        config(['templates.instruments' => true]);
        Storage::fake('media', ['url' => 'https://media.example.com']);
        // On, but nothing uploaded yet: still the synth.
        $this->getJson('/api/auth/config')->assertJsonPath('data.instrumentsIndexUrl', null);
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder])->assertSuccessful();
        $shown = $this->getJson('/api/auth/config');
        $this->assertMatchesContract($shown, 'auth.config', 200);
        $this->assertStringEndsWith('instruments/index.json', (string) $shown->json('data.instrumentsIndexUrl'));
    }

    public function test_a_folder_must_be_a_built_instrument(): void
    {
        $this->artisan('app:instruments:upload', ['name' => 'Piano!', 'version' => '1', 'folder' => $this->folder])->assertFailed();
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => 'latest', 'folder' => $this->folder])->assertFailed();

        File::put($this->folder.'/.DS_Store', 'x');
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder, '--check' => true])->expectsOutputToContain('no hidden files')->assertFailed();
        File::delete($this->folder.'/.DS_Store');

        File::put($this->folder.'/run.sh', 'x');
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder, '--check' => true])->expectsOutputToContain('not a sample')->assertFailed();
        File::delete($this->folder.'/run.sh');

        // The manifest's files must be there, at their size and hash.
        $this->manifest(['path' => 'samples/c4.mp3', 'bytes' => 3, 'sha256' => hash('sha256', 'other')]);
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder, '--check' => true])->expectsOutputToContain("doesn't match")->assertFailed();
        $this->manifest(['path' => 'samples/d4.mp3', 'bytes' => 3, 'sha256' => hash('sha256', 'mp3')]);
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder, '--check' => true])->expectsOutputToContain("isn't in the folder")->assertFailed();

        // In the shared format, for this name and version, with every key covered.
        $file = ['path' => 'samples/c4.mp3', 'bytes' => 3, 'sha256' => hash('sha256', 'mp3')];
        $check = fn () => $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder, '--check' => true]);
        $this->manifest($file, with: ['format' => 2]);
        $check()->expectsOutputToContain('format must be 1')->assertFailed();
        $this->manifest($file, 'organ');
        $check()->expectsOutputToContain("aren't piano 1")->assertFailed();
        $this->manifest($file, with: ['zones' => [['file' => 'samples/c4.mp3', 'frames' => 44100, 'encoderDelay' => 1105, 'onset' => 0.003, 'velLo' => 1, 'velHi' => 127, 'root' => 60, 'lo' => 0, 'hi' => 100, 'tune' => 0]]]);
        $check()->expectsOutputToContain('key 101')->assertFailed();
        $this->manifest($file, with: ['zones' => [['file' => 'samples/c4.mp3', 'frames' => 44100, 'velLo' => 1, 'velHi' => 127, 'root' => 60, 'lo' => 0, 'hi' => 127, 'tune' => 0]]]);
        $check()->expectsOutputToContain('zone 0')->assertFailed();
        $zone = ['file' => 'samples/c4.mp3', 'frames' => 44100, 'encoderDelay' => 1105, 'onset' => 0.003, 'velLo' => 1, 'velHi' => 127, 'root' => 60, 'lo' => 0, 'hi' => 127, 'tune' => 0];
        $this->manifest($file, with: ['zones' => [$zone, ['root' => 72, 'lo' => 70, 'hi' => 80] + $zone]]);
        $check()->expectsOutputToContain('key 70 has two zones')->assertFailed();
        // A drums zone names its key (null when it has none); two of one round robin don't overlap.
        $hit = ['file' => 'samples/c4.mp3', 'frames' => 44100, 'encoderDelay' => 1105, 'onset' => 0.003, 'velLo' => 1, 'velHi' => 127, 'piece' => 'kick', 'rr' => 1];
        $this->manifest($file, with: ['kind' => 'drums', 'zones' => [$hit]]);
        $check()->expectsOutputToContain('zone 0')->assertFailed();
        $this->manifest($file, with: ['kind' => 'drums', 'zones' => [['key' => 36] + $hit, ['key' => 36] + $hit]]);
        $check()->expectsOutputToContain('round robin 1, has two zones')->assertFailed();
        $this->manifest($file, with: ['kind' => 'drums', 'zones' => [['key' => 36] + $hit, ['key' => 36, 'rr' => 2] + $hit, ['key' => null, 'piece' => 'shaker'] + $hit]]);
        $check()->expectsOutputToContain('The folder is fine')->assertSuccessful();
        $this->manifest($file);
        $check()->expectsOutputToContain('The folder is fine')->assertSuccessful();

        File::put($this->folder.'/manifest.json', 'not json');
        $this->artisan('app:instruments:upload', ['name' => 'piano', 'version' => '1', 'folder' => $this->folder, '--check' => true])->expectsOutputToContain('valid JSON')->assertFailed();
        Storage::disk('media')->assertMissing('instruments/piano/1/manifest.json');
    }
}
