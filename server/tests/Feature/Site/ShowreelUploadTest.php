<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\Showreel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * app:showreel:upload: the renders are checked against the manifest in git, then go to
 * showreel/<version>/ on the media CDN with their types and a year's cache; --check uploads nothing.
 */
final class ShowreelUploadTest extends TestCase
{
    private string $public;

    private string $renders;

    /** @var list<string> */
    private const NAMES = [
        'stackapella-showreel-audio.m4a', 'stackapella-showreel-audio.opus', 'stackapella-showreel.vtt',
        'stackapella-showreel.webm', 'stackapella-showreel.mp4', 'stackapella-showreel-mobile.mp4', 'stackapella-showreel-poster.jpg',
        'stackapella-showreel-poster-960.webp', 'stackapella-showreel-poster-1440.webp', 'stackapella-showreel-poster-1920.webp',
        'stackapella-showreel-light.webm', 'stackapella-showreel-light.mp4', 'stackapella-showreel-light-mobile.mp4', 'stackapella-showreel-light-poster.jpg',
        'stackapella-showreel-light-poster-960.webp', 'stackapella-showreel-light-poster-1440.webp', 'stackapella-showreel-light-poster-1920.webp',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        $base = sys_get_temp_dir().'/showreel-'.bin2hex(random_bytes(4));
        $this->public = $base.'/public';
        $this->renders = $base.'/renders';
        File::ensureDirectoryExists($this->public.'/videos/showreel');
        File::ensureDirectoryExists($this->renders);
        $this->app->usePublicPath($this->public);

        $files = [];
        foreach (self::NAMES as $name) {
            File::put($this->renders.'/'.$name, 'render of '.$name);
            $files[] = ['name' => $name, 'bytes' => strlen('render of '.$name), 'sha256' => hash('sha256', 'render of '.$name)];
        }
        $this->manifest(['film' => 'Harmony, made by you', 'files' => $files]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->public));
        parent::tearDown();
    }

    /**
     * The same manifest in git and beside the renders.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function manifest(array $manifest, bool $commit = true): void
    {
        $json = (string) json_encode($manifest, JSON_PRETTY_PRINT);
        File::put($this->renders.'/manifest.json', $json);
        if ($commit) {
            File::put($this->public.'/videos/showreel/manifest.json', $json);
        }
    }

    public function test_the_render_goes_up_in_its_own_folder_with_its_types_and_a_years_cache(): void
    {
        $this->artisan('app:showreel:upload', ['--from' => $this->renders, '--check' => true])->expectsOutputToContain('17 files')->assertSuccessful();
        $this->assertSame([], Storage::disk('media')->allFiles());

        $this->artisan('app:showreel:upload', ['--from' => $this->renders])->expectsOutputToContain('Uploaded 17 files')->expectsOutputToContain('SHOWREEL_URL=')->assertSuccessful();

        $folder = 'showreel/'.Showreel::version();
        $this->assertCount(17, Storage::disk('media')->files($folder));
        Storage::disk('media')->assertExists($folder.'/stackapella-showreel.webm');
        $this->assertSame('render of stackapella-showreel.vtt', Storage::disk('media')->get($folder.'/stackapella-showreel.vtt'));

        // Run again (after a cut-off upload): what's there is skipped.
        $this->artisan('app:showreel:upload', ['--from' => $this->renders])->expectsOutputToContain('Uploaded 0 files (17 already there)')->assertSuccessful();
    }

    public function test_a_render_that_doesnt_match_the_manifest_in_git_is_refused(): void
    {
        $check = fn () => $this->artisan('app:showreel:upload', ['--from' => $this->renders, '--check' => true]);

        File::put($this->renders.'/stackapella-showreel.mp4', 'cut short');
        $check()->expectsOutputToContain("stackapella-showreel.mp4 doesn't match")->assertFailed();
        File::delete($this->renders.'/stackapella-showreel.mp4');
        $check()->expectsOutputToContain("stackapella-showreel.mp4 isn't in the folder")->assertFailed();

        // A newer render whose manifest isn't committed yet: the pages would ask for the old one.
        $this->manifest(['film' => 'A newer cut', 'files' => []], commit: false);
        $check()->expectsOutputToContain("isn't the one in git")->assertFailed();

        $this->manifest(['files' => [['name' => 'run.sh', 'bytes' => 1, 'sha256' => str_repeat('0', 64)]]]);
        $check()->expectsOutputToContain('run.sh: not a video')->assertFailed();

        $this->manifest(['files' => [['name' => 'stackapella-showreel.vtt', 'bytes' => 34, 'sha256' => hash('sha256', 'render of stackapella-showreel.vtt')]]]);
        $check()->expectsOutputToContain('missing what the pages use')->assertFailed();
        $this->assertSame([], Storage::disk('media')->allFiles());
    }
}
