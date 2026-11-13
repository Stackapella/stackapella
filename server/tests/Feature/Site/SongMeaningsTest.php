<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Pages\SongMeanings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A song's line meanings on its page (marketing's words band): every sung line as it is today, by stanza, with its
 * meaning in the page's language, matched by its sung text, so an edited line loses only its own meaning.
 */
final class SongMeaningsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    /** @var array<string, mixed> */
    private array $song;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/meanings-'.getmypid());
        File::deleteDirectory($this->dir);
        File::ensureDirectoryExists($this->dir);
        config(['content.meanings' => $this->dir]);
        $this->song = (array) json_decode((string) File::get(dirname(base_path()).'/web/templates/amazing-grace.json'), true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function file(array $lines): void
    {
        File::put($this->dir.'/amazing-grace.json', (string) json_encode(['song' => 'amazing-grace', 'templateVersion' => 4, 'sungLanguage' => 'en', 'lines' => $lines]));
    }

    public function test_every_sung_line_shows_with_its_meaning_where_one_is_current_in_the_pages_language(): void
    {
        $sung = SongMeanings::sungLines($this->song);
        $first = $sung[0][0];
        $second = $sung[0][1];
        $this->assertNotSame('', $first);

        // The first line's meaning is current; the second's was written for words since changed (stale).
        $this->file([
            ['section' => 0, 'phrase' => 0, 'sung' => $first, 'es' => 'Primera'],
            ['section' => 0, 'phrase' => 1, 'sung' => 'words no longer sung', 'es' => 'Vieja'],
        ]);
        $page = SongMeanings::forPage('amazing-grace', $this->song, 'es');
        $this->assertNotNull($page);
        $this->assertSame('en', $page['sungLanguage']);
        $this->assertSame(['sung' => $first, 'meaning' => 'Primera'], $page['lines'][0][0]);
        $this->assertSame(['sung' => $second], $page['lines'][0][1], 'a stale line stays as words, without a wrong meaning');
        $this->assertSame(count(array_filter($sung[0])), count($page['lines'][0]), 'every sung line is there');

        // Punctuation or case alone isn't an edit.
        $this->file([['section' => 0, 'phrase' => 0, 'sung' => strtoupper($first).'!', 'es' => 'Primera']]);
        $this->assertSame('Primera', SongMeanings::forPage('amazing-grace', $this->song, 'es')['lines'][0][0]['meaning'] ?? null);

        // None in the page's language, all stale, or no file: null, and the page falls back to its verses.
        $this->assertNull(SongMeanings::forPage('amazing-grace', $this->song, 'en'));
        $this->file([['section' => 0, 'phrase' => 0, 'sung' => 'nothing sung like this', 'es' => 'x']]);
        $this->assertNull(SongMeanings::forPage('amazing-grace', $this->song, 'es'));
        $this->assertNull(SongMeanings::forPage('no-such-song', $this->song, 'es'));
        $this->assertNull(SongMeanings::forPage('../secrets', $this->song, 'es'));
    }

    public function test_the_full_words_take_a_lines_meaning_where_they_have_none_of_their_own(): void
    {
        $story = ['fullWords' => ['intro' => '', 'stanzas' => [['label' => null, 'lines' => [['sung' => 'Amazing grace, how sweet the sound'], ['sung' => 'that saved a wretch like me', 'meaning' => 'Its own']]]]]];
        $lines = [[['sung' => 'Amazing grace, how sweet the sound', 'meaning' => 'Gracia'], ['sung' => 'that saved a wretch like me', 'meaning' => 'Other']]];

        $filled = SongMeanings::fillFullWords($story, $lines);
        $this->assertSame('Gracia', $filled['fullWords']['stanzas'][0]['lines'][0]['meaning']);
        $this->assertSame('Its own', $filled['fullWords']['stanzas'][0]['lines'][1]['meaning'], "the story's own meaning stays");
        $this->assertSame($story, SongMeanings::fillFullWords($story, null));
        $this->assertSame(['fullWords' => null], SongMeanings::fillFullWords(['fullWords' => null], $lines));
    }
}
