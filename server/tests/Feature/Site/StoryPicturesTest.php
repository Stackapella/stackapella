<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\SongParts;
use App\Support\Pages\SongStories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Every story picture on the song pages (the coordinator, 2026-11-13): alt text that says what it shows, its size for no
 * layout shift, a phone-sized version, and its credit and licence; and on a page, each figure lazy (the first sits
 * screens down) with an ImageObject for each in the page's data, so search shows it as licensable.
 */
final class StoryPicturesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->setLocale('en');
        parent::tearDown();
    }

    public function test_every_story_picture_says_what_it_shows_and_is_sized_credited_and_licensed(): void
    {
        $checked = 0;
        foreach (['en', 'es'] as $language) {
            app()->setLocale($language);
            foreach (SongStories::slugs() as $slug) {
                foreach ((array) (SongStories::read($slug, $language)['images'] ?? []) as $n => $image) {
                    $where = "{$language} {$slug} picture {$n}";
                    $alt = trim((string) ($image['alt'] ?? ''));
                    $this->assertGreaterThanOrEqual(15, mb_strlen($alt), "{$where}: alt text that says what it shows");
                    $this->assertDoesNotMatchRegularExpression('/^(an? )?(image|picture|photo|imagen|foto) (of|de)\b/iu', $alt, "{$where}: not \"image of\"");
                    $this->assertNotSame(mb_strtolower($alt), mb_strtolower(trim(strip_tags((string) ($image['caption'] ?? '')))), "{$where}: alt isn't the caption again");
                    $this->assertGreaterThan(0, (int) ($image['width'] ?? 0), "{$where}: width");
                    $this->assertGreaterThan(0, (int) ($image['height'] ?? 0), "{$where}: height");
                    $this->assertStringContainsString(' 480w', (string) ($image['srcset'] ?? ''), "{$where}: a phone-sized version");
                    $this->assertNotSame('', (string) ($image['licenceName'] ?? ''), "{$where}: its licence");
                    $this->assertNotSame('', (string) ($image['credit'] ?? '').(string) ($image['library'] ?? ''), "{$where}: its credit");
                    $checked++;
                }
            }
        }
        app()->setLocale('en');
        $this->assertGreaterThan(0, $checked, 'the stories have pictures to check');
    }

    public function test_a_song_page_draws_each_picture_lazy_and_sized_with_its_image_object(): void
    {
        config(['app.is_nexus' => true, 'app.coming_soon' => false]);
        $this->catalogue();
        $open = array_column(app(SongParts::class)->index(), 'slug');
        $slug = collect(SongStories::slugs())->first(static fn (string $s): bool => in_array($s, $open, true) && (SongStories::story($s)['images'] ?? []) !== []);
        if ($slug === null) {
            $this->markTestSkipped('no open song with a reviewed story with pictures in this content');
        }

        $html = (string) $this->get('/songs/'.$slug)->assertOk()->getContent();
        preg_match_all('#<figure class="s-fig">\s*<img ([^>]*)>#', $html, $figures);
        $this->assertNotEmpty($figures[1], "{$slug}: its pictures are drawn");
        foreach ($figures[1] as $attributes) {
            $this->assertMatchesRegularExpression('/ width="\d+" height="\d+"/', ' '.$attributes, "{$slug}: sized");
            $this->assertStringContainsString('loading="lazy"', $attributes);
            $this->assertStringContainsString('srcset="', $attributes);
            $this->assertStringContainsString('sizes="', $attributes);
            $this->assertDoesNotMatchRegularExpression('/alt=""/', $attributes, "{$slug}: alt text");
        }
        $this->assertSame(count($figures[1]), substr_count($html, '"@type":"ImageObject"'), "{$slug}: an ImageObject for each picture");
        $this->assertStringContainsString('"license":"', $html);
    }
}
