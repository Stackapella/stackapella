<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\SongStories;
use App\Support\Site\StructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A song story's pictures (Hillel, 2026-11-12: "images and links"): placed in the story, made into WebP sizes, served
 * from our own files, their licences only from the closed list with their evidence, and checked.
 */
final class SongMediaTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/media-'.getmypid());
        File::deleteDirectory($this->dir);
        File::copyDirectory(base_path('tests/Fixtures/stories'), $this->dir);
        config(['content.stories' => $this->dir]);

        // A picture of the right kind: a 1200 by 800 JPEG, and its record.
        File::ensureDirectoryExists($this->dir.'/media/test-song');
        $image = imagecreatetruecolor(1200, 800);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 120, 90, 60));
        imagejpeg($image, $this->dir.'/media/test-song/1.jpg', 82);
        $this->records([$this->record()]);
        // Placed in the story, English and Spanish, with a listen link.
        $this->place('test-song.md', "listen: National Jukebox, 1916 | https://example.com/jukebox || Archive | https://example.com/archive\noriginal: The autograph | https://example.com/autograph\n");
        $this->place('es/test-song.md', '');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        app()->setLocale('en');
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function record(array $with = []): array
    {
        return [...[
            'file' => '1.jpg',
            'alt' => ['en' => 'The church at Oberndorf', 'es' => 'La iglesia de Oberndorf'],
            'caption' => ['en' => 'The church, about 1900[^one].', 'es' => 'La iglesia, hacia 1900[^one].'],
            'date' => 'about 1900',
            'source' => 'A postcard',
            'library' => 'Library of Congress',
            'callNumber' => 'LOT 123',
            'sourceUrl' => 'https://example.com/item/1',
            'licence' => 'LoC-no-known-restrictions',
            'licenceEvidence' => 'https://example.com/rights "No known restrictions on publication."',
            'credit' => 'Library of Congress',
        ], ...$with];
    }

    /** @param  list<array<string, mixed>>  $records */
    private function records(array $records): void
    {
        File::put($this->dir.'/media/test-song/media.json', (string) json_encode($records));
    }

    private function place(string $file, string $front): void
    {
        $path = $this->dir.'/'.$file;
        $text = File::get($path);
        $text = preg_replace('/^---\n/', "---\n".$front, $text, 1);
        $text = (string) preg_replace('/^(## [^\n]+\n\n)/m', "$1![[1]]\n\n", (string) $text, 1);
        File::put($path, $text);
        if ($file === 'test-song.md') {
            // Its translation was made from this English.
            $es = $this->dir.'/es/test-song.md';
            File::put($es, (string) preg_replace('/^translatedFrom: .*$/m', 'translatedFrom: '.SongStories::hash($path), File::get($es)));
        }
    }

    public function test_a_placed_picture_is_made_into_webp_sizes_and_shows_with_its_credit_and_licence(): void
    {
        $this->assertStringContainsString('its WebP is missing or stale', implode("\n", SongStories::check('test-song')));
        $this->artisan('app:stories:images', ['song' => 'test-song'])->expectsOutputToContain('3 WebP files written')->assertSuccessful();
        $this->assertSame([], SongStories::check('test-song'));

        $story = SongStories::story('test-song');
        $this->assertNotNull($story);
        $this->assertStringContainsString("<!--figure:1-->\n", $story['sections'][0]['html']);
        $this->assertStringNotContainsString('<p>![[1]]</p>', $story['sections'][0]['html']);
        $image = $story['images']['1'];
        $this->assertSame([960, 640], [$image['width'], $image['height']]);
        $this->assertMatchesRegularExpression('#^/song-media/test-song/1-960\.webp\?v=[0-9a-f]{12}$#', $image['src']);
        $this->assertStringContainsString('1-480.webp', $image['srcset']);
        $this->assertStringContainsString('1-1440.webp', $image['srcset'], 'never upscaled: the 1440 is the 1200 original');
        $this->assertSame(['The church at Oberndorf', 'Library of Congress', 'LOT 123', 'No known restrictions (Library of Congress)', 'https://example.com/item/1'], [$image['alt'], $image['credit'], $image['callNumber'], $image['licenceName'], $image['sourceUrl']]);
        $this->assertStringContainsString('<a href="#source-1">[1]</a>', $image['caption']);
        $this->assertSame([['label' => 'National Jukebox, 1916', 'url' => 'https://example.com/jukebox'], ['label' => 'Archive', 'url' => 'https://example.com/archive']], $story['listen']);
        $this->assertSame([['label' => 'The autograph', 'url' => 'https://example.com/autograph']], $story['original']);
        $this->assertNull($story['hero']);

        // In Spanish, its Spanish words.
        app()->setLocale('es');
        $this->assertSame('La iglesia de Oberndorf', SongStories::story('test-song')['images']['1']['alt'] ?? null);
        app()->setLocale('en');

        // Served from our files, kept a year; anything else is a 404.
        $served = $this->get('/song-media/test-song/1-960.webp')->assertOk();
        $this->assertSame('image/webp', $served->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $served->headers->get('Cache-Control'));
        // An original is never served: no route takes it (the app shell's catch-all answers the address, never with it).
        $this->assertStringNotContainsString('image/', (string) $this->get('/song-media/test-song/1.jpg')->headers->get('Content-Type'));
        $this->get('/song-media/test-song/1-800.webp')->assertNotFound();
        $this->get('/song-media/no-song/1-960.webp')->assertNotFound();

        // Marked up as what it is.
        $objects = array_values(array_filter(StructuredData::song(['title' => 'Test Song', 'description' => 'A song.', 'pageUrl' => 'https://example.com/songs/test-song', 'genre' => [], 'credits' => []], $story), static fn (array $d): bool => $d['@type'] === 'ImageObject'));
        $this->assertCount(1, $objects);
        $this->assertSame(['Library of Congress', 'https://example.com/item/1', 'about 1900'], [$objects[0]['creditText'], $objects[0]['acquireLicensePage'], $objects[0]['dateCreated']]);
    }

    public function test_a_hero_is_drawn_by_the_heading_not_again_in_the_text(): void
    {
        $this->artisan('app:stories:images', ['song' => 'test-song'])->assertSuccessful();
        $path = $this->dir.'/test-song.md';
        File::put($path, preg_replace('/^---\n/', "---\nhero: 1\n", File::get($path), 1));

        $story = SongStories::story('test-song');
        $this->assertSame('1', $story['hero'] ?? null);
        $this->assertArrayHasKey('1', $story['images'] ?? []);
        $this->assertStringNotContainsString('<!--figure:1-->', $story['sections'][0]['html'] ?? '');
    }

    public function test_the_check_refuses_a_picture_whose_licence_or_record_isnt_right(): void
    {
        $this->artisan('app:stories:images', ['song' => 'test-song'])->assertSuccessful();

        $this->records([$this->record(['licence' => 'CC-BY-SA-4.0', 'licenceEvidence' => ' ', 'alt' => ['en' => 'The church']]), $this->record(['file' => '2.jpg'])]);
        $problems = implode("\n", SongStories::check('test-song'));
        foreach (['its licence must be one of PD-old, PD-US-gov, CC0, LoC-no-known-restrictions, CC-BY-4.0', 'no licenceEvidence', 'no alt in es', '2 is in media.json but never placed', '2: 2.jpg is missing'] as $expected) {
            $this->assertStringContainsString($expected, $problems);
        }

        // CC BY needs its credit text; a placed picture needs its record.
        $this->records([$this->record(['licence' => 'CC-BY-4.0'])]);
        $this->assertStringContainsString('1: no creditText', implode("\n", SongStories::check('test-song')));
        $this->records([]);
        $this->assertStringContainsString('![[1]] has no record in media.json', implode("\n", SongStories::check('test-song')));

        // A changed original makes its WebP stale; a picture over the caps fails.
        $this->records([$this->record()]);
        $big = imagecreatetruecolor(2000, 1000);
        imagejpeg($big, $this->dir.'/media/test-song/1.jpg', 82);
        $problems = implode("\n", SongStories::check('test-song'));
        $this->assertStringContainsString('its WebP is missing or stale', $problems);
        $this->assertStringContainsString('over the caps', $problems);
    }
}
