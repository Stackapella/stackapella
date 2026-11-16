<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\Showreel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing's film (pages/landing/_showreel.blade.php): not there until its manifest is, then the
 * band under the hero with each cut's files versioned by the manifest, the captions, the controls,
 * and its CDN host allowed for media and the poster.
 */
final class ShowreelTest extends TestCase
{
    use RefreshDatabase;

    private string $manifest;

    private bool $hadManifest = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = public_path('videos/showreel/manifest.json');
        $this->hadManifest = is_file($this->manifest);
    }

    protected function tearDown(): void
    {
        if (! $this->hadManifest && is_file($this->manifest)) {
            unlink($this->manifest);
        }
        parent::tearDown();
    }

    private function withManifest(): void
    {
        if (! $this->hadManifest) {
            if (! is_dir(dirname($this->manifest))) {
                mkdir(dirname($this->manifest), 0755, true);
            }
            file_put_contents($this->manifest, json_encode(['files' => []], JSON_THROW_ON_ERROR));
        }
    }

    public function test_without_its_files_the_landing_has_no_film(): void
    {
        if ($this->hadManifest) {
            $this->markTestSkipped('The renders are in place here.');
        }

        $this->get('/how-it-works')->assertOk()->assertDontSee('data-reel', false);
    }

    public function test_a_manifest_alone_shows_nothing_until_the_videos_can_be_reached(): void
    {
        $this->withManifest();
        config(['site.showreel.url' => '/videos/showreel']);
        if (is_file(public_path('videos/showreel/stackapella-showreel.mp4'))) {
            $this->markTestSkipped('The videos are rendered here.');
        }

        $this->get('/how-it-works')->assertOk()->assertDontSee('data-reel', false);
    }

    public function test_with_its_files_the_film_comes_from_the_cdn(): void
    {
        $this->withManifest();
        config(['site.showreel.url' => 'https://cdn.example.com/videos/showreel']);
        $version = substr((string) hash_file('sha256', $this->manifest), 0, 10);

        // (The film's page is How it works: the home page, since 2026-11-16, plays the song itself.)
        $page = $this->get('/how-it-works')->assertOk()
            // On the CDN each render is its own folder (app:showreel:upload), so a year's cache is safe.
            ->assertSee('data-webm="https://cdn.example.com/videos/showreel/'.$version.'/stackapella-showreel.webm"', false)
            ->assertSee('data-mobile="https://cdn.example.com/videos/showreel/'.$version.'/stackapella-showreel-light-mobile.mp4"', false)
            ->assertSee('<track kind="captions" srclang="en" label="'.__('pages.landing.reel.captions').'" src="/videos/showreel/stackapella-showreel.vtt?v='.$version.'"', false)
            ->assertSee('preload="none"', false)
            ->assertSee('data-reel-sound', false);

        $policy = (string) $page->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('#media-src [^;]*https://cdn\.example\.com#', $policy);
        $this->assertMatchesRegularExpression('#img-src [^;]*https://cdn\.example\.com#', $policy);
    }

    public function test_how_it_works_shows_the_film_on_its_first_screen_waiting_for_a_press(): void
    {
        $this->withManifest();
        config(['site.showreel.url' => 'https://cdn.example.com/videos/showreel']);

        $html = (string) $this->get('/how-it-works')->assertOk()->getContent();
        $first = (string) strstr((string) strstr($html, 'id="page-h"'), 'id="first-h"', true);

        // The landing's frame in the head, beside the headline, in place of the screenshot: a poster and a play button,
        // nothing fetched until it's pressed, sized for its column. The landing's band and its heading stay the landing's.
        $this->assertMatchesRegularExpression('#<figure class="s-reel" data-reel\s+data-reel-press\s#', $first);
        $this->assertStringContainsString('preload="none"', $first);
        $this->assertStringContainsString('data-reel-start', $first);
        $this->assertStringContainsString('sizes="(min-width: 1200px) 580px, (min-width: 900px) 47vw, 100vw"', $first);
        $this->assertStringNotContainsString('learn-a-part--notes', $html);
        $this->assertStringNotContainsString('id="reel-h"', $html);
        $this->assertSame(1, substr_count($html, '<script type="module" src="/js/public/showreel.js">'));

        // The home page has no film: it plays the song itself.
        $this->assertStringNotContainsString('data-reel', (string) $this->get('/')->assertOk()->getContent());
    }

    public function test_how_it_works_keeps_its_screenshot_while_the_films_files_are_missing(): void
    {
        config(['app.is_nexus' => true, 'app.coming_soon' => false, 'site.showreel.url' => '/videos/showreel']);
        app(Flags::class)->set('lang_es', 'on', null, 'test');
        // The Spanish film's renders are only ever on the CDN, so here its page has a manifest and no videos.
        if (! is_file(public_path('videos/showreel/es/manifest.json')) || is_file(public_path('videos/showreel/es/stackapella-showreel.mp4'))) {
            $this->markTestSkipped('The Spanish film is not in the state this checks.');
        }

        $this->get('/es/how-it-works')->assertOk()->assertDontSee('data-reel', false)->assertSee('s-heroshot', false);
    }

    public function test_the_films_address_comes_from_the_cdn_with_no_setting_of_its_own(): void
    {
        $url = function (array $env): string {
            foreach ($env as $name => $value) {
                $value === null ? putenv($name) : putenv($name.'='.$value);
                $_ENV[$name] = $_SERVER[$name] = $value;
            }

            try {
                return (string) (require config_path('site.php'))['showreel']['url'];
            } finally {
                foreach (array_keys($env) as $name) {
                    putenv($name);
                    unset($_ENV[$name], $_SERVER[$name]);
                }
            }
        };

        // Production: Spaces with a CDN, and no SHOWREEL_URL.
        $this->assertSame('https://cdn.example.com/showreel', $url(['MEDIA_DISK' => 'spaces_media', 'CDN_URL' => 'https://cdn.example.com/', 'SHOWREEL_URL' => null]));
        // SHOWREEL_URL still wins.
        $this->assertSame('https://films.example.com/reel', $url(['MEDIA_DISK' => 'spaces_media', 'CDN_URL' => 'https://cdn.example.com', 'SHOWREEL_URL' => 'https://films.example.com/reel/']));
        // Locally, or Spaces without a CDN: this server's copy.
        $this->assertSame('/videos/showreel', $url(['MEDIA_DISK' => 'media_local', 'CDN_URL' => 'https://cdn.example.com', 'SHOWREEL_URL' => null]));
        $this->assertSame('/videos/showreel', $url(['MEDIA_DISK' => 'spaces_media', 'CDN_URL' => null, 'SHOWREEL_URL' => null]));

        // The pages' policy lets the derived host's film and poster through.
        config(['site.showreel.url' => 'https://cdn.example.com/showreel']);
        $policy = (string) $this->get('/how-it-works')->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('#media-src [^;]*https://cdn\.example\.com#', $policy);
        $this->assertMatchesRegularExpression('#img-src [^;]*https://cdn\.example\.com#', $policy);
    }

    public function test_each_language_shows_its_own_film_and_english_until_it_has_one(): void
    {
        $this->withManifest();
        config(['site.showreel.url' => 'https://media.example.com/showreel']);
        // A language that will never have a film (the real ones, Spanish first, have their manifests in git).
        $spanish = public_path('videos/showreel/zz/manifest.json');
        $this->assertFileDoesNotExist($spanish, 'a real render is never overwritten by this test');

        try {
            // No film in that language yet: its page shows the English one.
            $this->assertSame('en', Showreel::language('zz'));
            $this->assertSame(Showreel::files('en')['dark']['mp4'], Showreel::files('zz')['dark']['mp4']);

            mkdir(dirname($spanish), 0755, true);
            file_put_contents($spanish, json_encode(['lang' => 'zz', 'files' => []], JSON_THROW_ON_ERROR));
            $es = Showreel::files('zz');
            $this->assertSame('zz', $es['language']);
            $this->assertSame(substr((string) hash_file('sha256', $spanish), 0, 10), Showreel::version('zz'));
            $this->assertSame('https://media.example.com/showreel/'.Showreel::version('zz').'/stackapella-showreel.mp4', $es['dark']['mp4']);
            $this->assertNotSame(Showreel::version('en'), Showreel::version('zz'));
            $this->assertStringStartsWith('/videos/showreel/zz/stackapella-showreel.vtt?v=', $es['captions']);
            $this->assertSame('en', Showreel::files('en')['language'], 'English stays as it was');
            $this->assertSame('en', Showreel::language('de'), 'a language with no film falls back to English');
            // The real Spanish film, once its manifest is in git: Spanish pages show it, at its own version.
            $real = public_path('videos/showreel/es/manifest.json');
            if (is_file($real)) {
                $this->assertSame('es', Showreel::language('es'));
                $this->assertSame(substr((string) hash_file('sha256', $real), 0, 10), Showreel::version('es'));
            }

            // And the upload command takes it by --lang, refusing a language with no film.
            $this->artisan('app:showreel:upload', ['--lang' => 'de', '--check' => true])->assertFailed();
        } finally {
            if (is_file($spanish)) {
                unlink($spanish);
            }
            if (is_dir(dirname($spanish)) && (scandir(dirname($spanish)) ?: []) === ['.', '..']) {
                rmdir(dirname($spanish));
            }
        }
    }
}
