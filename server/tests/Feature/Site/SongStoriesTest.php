<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Pages\SongStories;
use App\Support\Site\SitemapPaths;
use App\Support\Site\StructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The songs' stories (docs/songs/story-guide.md; Hillel, 2026-11-12: "Make people come here instead of wikipeadia"):
 * read from their files, cited, translated only while current, and checked against the guide.
 */
final class SongStoriesTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/stories-'.getmypid());
        File::deleteDirectory($this->dir);
        File::copyDirectory(base_path('tests/Fixtures/stories'), $this->dir);
        config(['content.stories' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        app()->setLocale('en');
        parent::tearDown();
    }

    private function write(string $find, string $replace, string $file = 'test-song.md'): void
    {
        $path = $this->dir.'/'.$file;
        $text = File::get($path);
        $this->assertStringContainsString($find, $text, 'the fixture has what the test changes');
        File::put($path, str_replace($find, $replace, $text));
    }

    public function test_a_story_reads_as_the_guide_lays_it_out(): void
    {
        $this->assertSame([], SongStories::check('test-song'));
        $story = SongStories::story('test-song');
        $this->assertNotNull($story);

        $this->assertStringContainsString('A song made for the tests, sung by nobody<sup class="s-cite"><a href="#source-1">[1]</a></sup>.', $story['opening']);
        $this->assertSame(['origins', 'words', 'sung', 'facts', 'singing', 'questions', 'next'], array_column($story['sections'], 'key'));
        $this->assertSame('where-it-comes-from', $story['sections'][0]['id']);
        // A source may be a print citation with no address (one whose publisher forbids linking, the Oklahoma Historical
        // Society's): it reads as plain text, has no link, and the check takes it.
        $this->assertSame(['id' => 'two', 'n' => 2, 'html' => '<em>A Hymnal</em> (Boston, 1818), no. 12.', 'url' => null], $story['sources'][1]);
        $this->assertArrayNotHasKey('url', StructuredData::song(['title' => 'T', 'description' => 'D', 'pageUrl' => 'https://example.com/songs/t', 'genre' => ['hymn'], 'credits' => []], $story)[1]['citation'][1]);
        $this->assertStringNotContainsString('Timeline', $story['sections'][0]['html'], 'the timeline is its own list');
        $this->assertSame([['year' => '1816', 'html' => 'written down <sup class="s-cite"><a href="#source-1">[1]</a></sup>'], ['year' => '1818', 'html' => 'printed <sup class="s-cite"><a href="#source-2">[2]</a></sup>']], $story['timeline']);

        // Old songs' dates, in English and Spanish (the courses lead): every form reads, nothing is dropped.
        $forms = ['13th century', 'late 12th century', '9th century BCE', '1911 to 1912', '1913 or 1914', 'About 1505', 'c. 1790', 'after 1883', '1820s', '1583/4', '1526/27', '1700?', 'hacia 1846 a 1850', 'después de 1883', 'finales del siglo XII', 'siglo IX a. C.', 'década de 1810'];
        $this->write('- 1818: printed [^two]', '- 1818: printed [^two]'.implode('', array_map(static fn (string $f): string => "\n- {$f}: a row [^one]", $forms)));
        $this->assertSame(['1816', '1818', ...$forms], array_column(SongStories::read('test-song')['timeline'] ?? [], 'year'));
        $this->assertSame([], SongStories::check('test-song'));

        // A row with no date is a problem, never dropped silently.
        $this->write('- 1818: printed [^two]', "- 1818: printed [^two]\n- printed again later [^two]");
        $this->assertStringContainsString('a Timeline row with no date', implode("\n", SongStories::check('test-song')));
        $this->assertCount(1, $story['didYouKnow']);
        $this->assertStringContainsString('href="/songs/test-song/alto"', $story['sections'][4]['html']);
        $this->assertSame(['Who wrote it?', 'Is it sung in church?'], array_column($story['questions'], 'question'));
        $this->assertSame('The tests did, in 1816.', $story['questions'][0]['text'], 'the plain answer for FAQPage, its citation left out');
        $this->assertSame([['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'reason' => 'the same meter.'], ['slug' => 'silent-night', 'title' => 'Silent Night', 'reason' => 'a good next step.']], $story['next']);
        $this->assertSame([1, 2, 3], array_column($story['sources'], 'n'));
        $this->assertSame('https://example.com/book', $story['sources'][0]['url']);
        $this->assertSame('Ann Writer, <em>The Book of Songs</em> (London: Printer, 1816), p. 4.', $story['sources'][0]['html']);
        $this->assertNull($story['sources'][1]['url']);
        $this->assertSame(['words' => 'Ann Writer (1790 to 1850)', 'tune' => 'traditional', 'tuneName' => 'TESTING', 'meter' => 'CM (8.6.8.6)', 'earliestPrinting' => '1818, A Hymnal', 'language' => 'English'], $story['facts']);
        $this->assertSame(2, $story['earliestPrintingSource']);
        $this->assertSame('Q1234', $story['wikidata']);
        $this->assertSame(['text' => 'Sing it once, then sing it again.', 'source' => 'Ann Writer, 1816'], $story['quote']);
        $this->assertSame(['2026-11-12', '2026-11-12'], [$story['updated'], $story['checked']]);
        $this->assertSame(['test song history', 'who wrote test song'], $story['searches']);
        $this->assertSame('2026-11-12', SongStories::updated('test-song'));
    }

    public function test_a_translation_shows_only_while_it_was_made_from_todays_english(): void
    {
        // A Spanish lead's accented words count once each: 39 is under the limit.
        $this->write('lead: Una canción hecha para las pruebas, con sus fuentes y preguntas.', 'lead: '.trim(str_repeat('canción Janucá añoranza ', 13)), 'es/test-song.md');
        $this->assertStringNotContainsString('lead is', implode("\n", SongStories::check('test-song')));

        app()->setLocale('es');
        $story = SongStories::story('test-song');
        $this->assertSame('es', $story['language'] ?? null);
        $this->assertSame(['origins', 'words', 'sung', 'facts', 'singing', 'questions', 'next'], array_column($story['sections'] ?? [], 'key'), 'its sections known by their place');
        $this->assertSame('¿Quién la escribió?', $story['questions'][0]['question'] ?? null);
        $this->assertCount(2, $story['timeline'] ?? []);

        // The English changes: the Spanish is stale, so the English shows until it's redone.
        $this->write('sung by nobody', 'sung by almost nobody');
        $this->assertSame('en', SongStories::story('test-song')['language'] ?? null);
        $this->assertSame([], SongStories::check('test-song'), 'stale shows English, it is not a failure');
    }

    public function test_only_a_reviewed_story_shows(): void
    {
        $this->write('reviewed: true', 'reviewed: false');
        $this->assertNull(SongStories::story('test-song'));
        $this->assertNotNull(SongStories::read('test-song'));
        $this->assertNull(SongStories::story('no-such-song'));
        $this->assertNull(SongStories::story('../secrets'));
    }

    public function test_the_check_fails_a_story_the_guide_would(): void
    {
        $this->write('The tests did, in 1816[^one].', 'The tests did[^four], in 1816 '."\u{2014}".' see https://example.com.');
        $this->write('- [^three]: Bo Scholar', "- [^five]: Nobody cites this\n- [^three]: Bo Scholar");
        $this->write('description: Written down', 'description: '.str_repeat('Long ', 40));
        $this->write('lead: A song made', 'lead: '.str_repeat('word ', 40).'A song made');
        $this->write("## How it is sung\n\nSlowly, in four parts[^two].", "## How it is sung\n\n");
        $this->write('earliestPrintingSource: two', 'earliestPrintingSource: six');

        $problems = implode("\n", SongStories::check('test-song'));
        foreach (['[^four] has no source', 'source [^five] is never cited', 'an em dash', 'an address outside Sources', 'description is over 160', 'lead is 40 words', '"How it is sung" is empty', 'earliestPrintingSource six is not a source', 'test-song (es): its source ids differ from the English'] as $expected) {
            $this->assertStringContainsString($expected, $problems);
        }

        $this->artisan('app:stories:check', ['song' => 'test-song'])->assertFailed();
    }

    public function test_the_check_fails_a_quoted_front_matter_value_but_not_quotes_inside_one(): void
    {
        $this->write('title: Test Song: history, meaning and the words explained', 'title: "Test Song: history, meaning and the words explained"');
        $this->write('earliestPrinting: 1818, A Hymnal', 'earliestPrinting: "A Hymnal" (1818), and "Songs" (1820)');

        $problems = SongStories::check('test-song');
        $this->assertSame(['test-song: title is in quotes: front matter values aren\'t quoted (the line splits on its first colon)'], $problems);
    }

    public function test_the_check_wants_the_guides_sections_in_order(): void
    {
        $this->write("## Sing next\n\n- [Amazing Grace](/songs/amazing-grace): the same meter.\n- [Silent Night](/songs/silent-night): a good next step.\n\n", '');
        $this->write('## Did you know?', '## Fun facts');
        $this->write("### Timeline\n\n- 1816: written down [^one]\n- 1818: printed [^two]", 'No timeline yet[^one][^two].');
        $problems = implode("\n", SongStories::check('test-song'));

        $this->assertStringContainsString('no "Sing next"', $problems);
        $this->assertStringContainsString('"Fun facts" is not one of the guide\'s sections', $problems);
        $this->assertStringContainsString('has no ### Timeline rows', $problems);
        $this->assertStringContainsString('test-song (es): it has 8 sections, the English 7', $problems);

        $this->artisan('app:stories:check')->assertFailed();
        File::copyDirectory(base_path('tests/Fixtures/stories'), $this->dir);
        $this->artisan('app:stories:check')->expectsOutputToContain('1 story checked, 0 problems.')->assertSuccessful();
    }

    public function test_the_page_marks_up_the_story_it_shows_and_the_sitemap_dates_its_edits(): void
    {
        $song = ['title' => 'Test Song', 'description' => 'A song.', 'pageUrl' => 'https://example.com/songs/test-song', 'genre' => ['hymn'], 'credits' => []];
        $this->assertCount(1, StructuredData::song($song), 'no story: the composition alone, as before');

        [$composition, $article, $faq] = StructuredData::song($song, SongStories::story('test-song'));
        $this->assertSame(['@type' => 'Person', 'name' => 'Ann Writer'], $composition['lyricist']);
        $this->assertArrayNotHasKey('composer', $composition, 'a traditional tune names no composer');
        $this->assertSame(['1816', 'https://www.wikidata.org/wiki/Q1234'], [$composition['dateCreated'], $composition['sameAs']]);
        $this->assertSame(['Article', '2026-11-12', 3], [$article['@type'], $article['dateModified'], count($article['citation'])]);
        $this->assertSame(['@type' => 'CreativeWork', 'name' => 'Ann Writer, The Book of Songs (London: Printer, 1816), p. 4.', 'url' => 'https://example.com/book'], $article['citation'][0]);
        $this->assertSame(['FAQPage', 'Who wrote it?', 'The tests did, in 1816.'], [$faq['@type'], $faq['mainEntity'][0]['name'], $faq['mainEntity'][0]['acceptedAnswer']['text']]);

        // A real song's story, edited after the song: the song page's lastmod follows the story.
        app(SyncOfficialTemplates::class)->handle();
        File::copy($this->dir.'/test-song.md', $this->dir.'/amazing-grace.md');
        File::put($this->dir.'/amazing-grace.md', str_replace('updated: 2026-11-12', 'updated: 2099-01-01', File::get($this->dir.'/amazing-grace.md')));
        $this->assertSame('2099-01-01', app(SitemapPaths::class)->lastModified()['/songs/amazing-grace'] ?? null);
    }

    public function test_the_full_words_leave_the_words_section_for_full_words(): void
    {
        $song = ['title' => 'Test Song', 'description' => 'A song.', 'pageUrl' => 'https://example.com/songs/test-song', 'genre' => ['hymn'], 'credits' => []];
        $this->assertNull(SongStories::story('test-song')['fullWords'] ?? null, 'no block, no full words');

        // An English song on its English page: an intro with its citation, then stanzas sung as written.
        $this->write("The words mean what they say[^three].\n", "The words mean what they say[^three].\n\n### The full words\n\nAs Ann Writer printed them[^one].\n\n**Verse 1**\nSing it *once*,\nthen sing it again.\n\nThe second time\nsofter[^two].\n");
        $story = SongStories::story('test-song');
        $words = $story['fullWords'];
        $this->assertStringContainsString('As Ann Writer printed them<sup class="s-cite"><a href="#source-1">[1]</a></sup>', $words['intro']);
        $this->assertSame([
            ['label' => 'Verse 1', 'lines' => [['sung' => 'Sing it once,'], ['sung' => 'then sing it again.']]],
            ['label' => null, 'lines' => [['sung' => 'The second time'], ['sung' => 'softer.']]],
        ], $words['stanzas']);
        $section = collect($story['sections'])->firstWhere('key', 'words');
        $this->assertStringNotContainsString('full words', $section['html']);
        $this->assertStringNotContainsString('sing it again', $section['html']);
        $this->assertSame([], SongStories::check('test-song'));

        // Its markup carries the sung text, stanzas apart, in the song's language; withheld, it has none.
        $composition = StructuredData::song($song, $story)[0];
        $this->assertSame(['@type' => 'CreativeWork', 'text' => "Sing it once,\nthen sing it again.\n\nThe second time\nsofter.", 'inLanguage' => 'en'], $composition['lyrics']);
        $hidden = SongStories::withoutWords($story);
        $this->assertNull($hidden['fullWords']);
        $this->assertArrayNotHasKey('lyrics', StructuredData::song($song, $hidden)[0]);
    }

    public function test_full_words_in_another_script_or_language_carry_their_meaning(): void
    {
        $this->write('language: English', 'language: Hebrew');
        $this->write("The words mean what they say[^three].\n", "The words mean what they say[^three].\n\n### The full words\n\nThe hymn as it is sung[^one].\n\n> מָעוֹז צוּר\n*Ma'oz tzur*\nStronghold, rock\n> plain original\nits meaning\n");
        $this->assertSame([['script' => 'מָעוֹז צוּר', 'scriptLang' => 'he', 'sung' => "Ma'oz tzur", 'meaning' => 'Stronghold, rock'], ['sung' => 'plain original', 'meaning' => 'its meaning']], SongStories::story('test-song')['fullWords']['stanzas'][0]['lines']);
        $this->assertSame('he', SongStories::story('test-song')['sungLanguage']);

        // A "### The full words" that isn't the words section's last subsection is prose, as before.
        $this->write("its meaning\n", "its meaning\n\n### Afterwards\n\nMore.\n");
        $this->assertNull(SongStories::story('test-song')['fullWords']);
    }
}
