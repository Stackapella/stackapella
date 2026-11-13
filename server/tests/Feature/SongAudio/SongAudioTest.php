<?php

declare(strict_types=1);

namespace Tests\Feature\SongAudio;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Support\SongAudio\SongAudioFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A song's whole-song audio (Hillel: "expose the main audio for the song", in stereo): the showreel agent's renders go
 * up checked by their bytes and sha256 and into the committed manifest, the song page plays the current version's and
 * names it as a MusicRecording, and app:song-audio:stale says which songs to render again.
 */
final class SongAudioTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private Template $song;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        $this->dir = storage_path('framework/testing/song-audio-'.getmypid());
        File::deleteDirectory($this->dir);
        File::ensureDirectoryExists($this->dir.'/renders');
        config(['content.song_audio' => $this->dir.'/song_audio.json']);

        $this->song = Template::factory()->create(['slug' => 'a-song', 'title' => 'A Song', 'access' => TemplateAccess::Free]);
        $data = (array) TemplateVersion::factory()->make()->data;
        $version = TemplateVersion::factory()->create(['template_id' => $this->song->id, 'version' => 2, 'reviewed' => true, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data))]);
        $this->song->forceFill(['current_version_id' => $version->id])->save();
        $this->song->refresh();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** @return array<string, null> every part of the song, sung by the app's guide voice */
    private function guides(): array
    {
        return array_fill_keys(array_column((array) (((array) $this->song->currentVersion?->data)['parts'] ?? []), 'key'), null);
    }

    /**
     * A render folder as render.py writes it.
     *
     * @param  array<string, mixed>  $with
     */
    private function render(string $mp3 = 'mp3 bytes', array $with = []): void
    {
        $files = [];
        foreach (['mp3' => $mp3, 'm4a' => 'm4a bytes'] as $ext => $bytes) {
            File::put("{$this->dir}/renders/a-song.{$ext}", $bytes);
            $files[] = ['name' => "a-song.{$ext}", 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
        }
        $entry = ['slug' => 'a-song', 'title' => 'A Song', 'version' => 2, 'tempo' => 90, 'key' => 'G', 'guides' => $this->guides(),
            'credit' => 'Tune traditional. Arrangement: Stackapella.', 'duration' => 42.59, 'channels' => 2, 'lufs' => -16.0, 'files' => $files, ...$with];
        File::put($this->dir.'/renders/manifest.json', (string) json_encode(['source' => 'server/resources/promo/songaudio', 'songs' => ['a-song' => $entry]]));
    }

    public function test_a_render_goes_up_checked_into_the_manifest_and_its_page_plays_it(): void
    {
        $this->render();
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--check' => true])->expectsOutput('1 songs check out, 0 skipped.')->assertSuccessful();
        $this->assertSame([], Storage::disk('media')->allFiles());
        $this->assertSame([], SongAudioFiles::manifest(), 'a check writes nothing');
        // On a local media disk, never by chance: the keys alone left it local once, and the CDN had none of the files.
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders'])->expectsOutputToContain('set MEDIA_DISK=spaces_media')->assertFailed();
        $this->assertSame([], Storage::disk('media')->allFiles());

        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--local' => true])->expectsOutput('2 files uploaded, 0 already there, 0 songs skipped; 1 songs in data/song_audio.json.')->assertSuccessful();
        $key = 'song-audio/a-song/2-'.substr(hash('sha256', 'mp3 bytes'), 0, 8).'.mp3';
        $this->assertSame('mp3 bytes', Storage::disk('media')->get($key));
        $this->assertSame(42.59, SongAudioFiles::manifest()['a-song']['duration']);
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--local' => true])->expectsOutput('0 files uploaded, 2 already there, 0 songs skipped; 1 songs in data/song_audio.json.')->assertSuccessful();

        $page = (string) $this->get('/songs/a-song')->assertOk()->getContent();
        $this->assertStringContainsString('MusicRecording', $page);
        $this->assertStringContainsString('PT0M43S', $page);
        $url = Storage::disk('media')->url($key);
        $this->assertTrue(str_contains($page, '"contentUrl":"'.$url.'"') || str_contains($page, '"contentUrl":"'.str_replace('/', '\/', $url).'"'), 'the MusicRecording plays the mp3');

        // A song stored as Plus is an open song all the same: a guest hears it (Hillel, 2026-11-13: "it should be easy to
        // hear the song on the song page"), and the render is the hero player's source for all its parts.
        $this->song->forceFill(['access' => TemplateAccess::Plus])->save();
        $this->app->forgetScopedInstances();
        $plus = $this->get('/songs/a-song')->assertOk();
        $this->assertStringContainsString('MusicRecording', (string) $plus->getContent());
        $this->assertStringContainsString('id="song-mixer"', (string) $plus->getContent());
        $m4a = Storage::disk('media')->url('song-audio/a-song/2-'.substr(hash('sha256', 'm4a bytes'), 0, 8).'.m4a');
        $this->assertSame(['m4a' => $m4a, 'mp3' => $url], $plus->viewData('line')['audio']);
    }

    public function test_an_upload_of_one_song_keeps_every_other_song_in_the_manifest(): void
    {
        // Re-renders come a song or two at a time (Jerusalem's v2, the coordinator, 2026-11-13): the committed manifest
        // keeps every song not in the folder as it was, and takes the folder's songs' new entries.
        $other = ['slug' => 'other-song', 'version' => 4, 'duration' => 61.5, 'files' => [['name' => 'other-song.mp3', 'bytes' => 3, 'sha256' => str_repeat('a', 64)]]];
        File::put($this->dir.'/song_audio.json', (string) json_encode(['source' => 'x', 'songs' => ['other-song' => $other]]));

        $this->render();
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--local' => true])->expectsOutput('2 files uploaded, 0 already there, 0 songs skipped; 2 songs in data/song_audio.json.')->assertSuccessful();
        $this->assertSame(['a-song', 'other-song'], array_keys(SongAudioFiles::manifest()));
        $this->assertSame($other, SongAudioFiles::manifest()['other-song'], 'kept as it was');

        // The same song rendered again (a new take of it): replaced, and the other still kept.
        $this->render('new mp3 bytes', ['duration' => 50.5]);
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--local' => true])->assertSuccessful();
        $this->assertSame([50.5, $other], [SongAudioFiles::manifest()['a-song']['duration'], SongAudioFiles::manifest()['other-song']]);
    }

    public function test_a_render_that_doesnt_match_waits(): void
    {
        $this->render();
        File::put($this->dir.'/renders/a-song.mp3', 'other bytes');
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--local' => true])->expectsOutputToContain('a-song: skipped, a-song.mp3: missing, or not the bytes and sha256')->assertFailed();

        $this->render(with: ['files' => [['name' => '../a-song.mp3', 'bytes' => 1, 'sha256' => 'x']]]);
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--local' => true])->expectsOutputToContain('not <slug>.mp3 or <slug>.m4a')->assertFailed();

        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/nowhere', '--local' => true])->expectsOutputToContain('No manifest.json')->assertFailed();
        $this->assertSame([], Storage::disk('media')->allFiles());
        $this->assertSame([], SongAudioFiles::manifest());
        $this->assertStringNotContainsString('MusicRecording', (string) $this->get('/songs/a-song')->assertOk()->getContent());
    }

    public function test_stale_names_the_songs_to_render_again_and_an_old_render_stops_playing(): void
    {
        $this->artisan('app:song-audio:stale')->expectsOutput('a-song  no render')->assertSuccessful();

        $this->render();
        $this->artisan('app:song-audio:upload', ['folder' => $this->dir.'/renders', '--local' => true])->assertSuccessful();
        $this->artisan('app:song-audio:stale')->expectsOutput('0 to render.')->assertSuccessful();

        // A real voice becomes a part's guide: the mix no longer has it.
        TemplateGuide::factory()->create(['template_version_id' => $this->song->current_version_id, 'part_key' => array_key_first($this->guides())]);
        $this->artisan('app:song-audio:stale', ['--slugs' => true])->expectsOutput('a-song')->assertSuccessful();
        $this->artisan('app:song-audio:stale')->expectsOutput('a-song  its guide voices changed')->assertSuccessful();

        // A new version of the song: the render plays the old notes, and the page stops playing it.
        $data = (array) $this->song->currentVersion?->data;
        $next = TemplateVersion::factory()->create(['template_id' => $this->song->id, 'version' => 3, 'reviewed' => true, 'data' => $data, 'sha256' => hash('sha256', 'v3')]);
        $this->song->forceFill(['current_version_id' => $next->id])->save();
        $this->app->forgetScopedInstances();
        $this->artisan('app:song-audio:stale')->expectsOutput('a-song  rendered from version 2, now 3')->assertSuccessful();
        $this->assertStringNotContainsString('MusicRecording', (string) $this->get('/songs/a-song')->assertOk()->getContent());
    }
}
