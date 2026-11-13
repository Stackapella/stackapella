<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Template;
use App\Support\Flags;
use App\Support\Pages\SongPage;
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

        $this->assertStringContainsString('A song made for the tests, sung by nobody<sup class="s-cite"><a href="#source-1" aria-label="Source 1">[1]</a></sup>.', $story['opening']);
        $this->assertSame(['origins', 'words', 'sung', 'facts', 'singing', 'questions', 'next'], array_column($story['sections'], 'key'));
        $this->assertSame('where-it-comes-from', $story['sections'][0]['id']);
        // A source may be a print citation with no address (one whose publisher forbids linking, the Oklahoma Historical
        // Society's): it reads as plain text, has no link, and the check takes it.
        $this->assertSame(['id' => 'two', 'n' => 2, 'html' => '<em>A Hymnal</em> (Boston, 1818), no. 12.', 'url' => null], $story['sources'][1]);
        $this->assertArrayNotHasKey('url', StructuredData::song(['title' => 'T', 'description' => 'D', 'pageUrl' => 'https://example.com/songs/t', 'genre' => ['hymn'], 'credits' => []], $story)[1]['citation'][1]);
        $this->assertStringNotContainsString('Timeline', $story['sections'][0]['html'], 'the timeline is its own list');
        $this->assertSame([['year' => '1816', 'html' => 'written down <sup class="s-cite"><a href="#source-1" aria-label="Source 1">[1]</a></sup>'], ['year' => '1818', 'html' => 'printed <sup class="s-cite"><a href="#source-2" aria-label="Source 2">[2]</a></sup>']], $story['timeline']);

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

    public function test_the_check_fails_an_escaped_quote_in_front_matter(): void
    {
        // As O the Beautiful Treasure's description had it, shown on its page backslashes and all (2026-11-13).
        $this->write('earliestPrinting: 1818, A Hymnal', 'earliestPrinting: the Shakers\' \\"Holy Ground\\" (1818)');

        $this->assertSame(['test-song: earliestPrinting has a backslash: front matter values are shown as written, so quotes need no escaping'], SongStories::check('test-song'));
        $this->artisan('app:stories:check', ['song' => 'test-song'])->assertFailed();
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
        $this->assertStringContainsString('As Ann Writer printed them<sup class="s-cite"><a href="#source-1" aria-label="Source 1">[1]</a></sup>', $words['intro']);
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

    public function test_a_song_page_shows_the_storys_full_words_with_labels_and_folds_a_long_song_after_three(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $stanzas = implode("\n\n", array_map(static fn (int $n): string => "**Verse {$n}**\nLine one of verse {$n},\nline two of verse {$n}.", range(1, 5)));
        $this->write("The words mean what they say[^three].\n", "The words mean what they say[^three].\n\n### The full words\n\nAs Ann Writer printed them[^one].\n\n{$stanzas}\n");
        File::copy($this->dir.'/test-song.md', $this->dir.'/amazing-grace.md');
        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();

        // The story's words, not the template's verses: its note with its source, each stanza's label, sung in its language.
        $this->assertStringContainsString('<div class="s-prose s-sg-words-intro"><p>As Ann Writer printed them<sup class="s-cite"><a href="#source-1" aria-label="Source 1">[1]</a></sup>', $html);
        $this->assertStringContainsString('<span class="s-sg-label">Verse 1</span>', $html);
        $this->assertStringContainsString('<span class="s-sg-sung" translate="no" lang="en">Line one of verse 1,</span>', $html);
        $verse = SongPage::verses(Template::query()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail())[0][0] ?? null;
        $this->assertIsString($verse, 'the template has verses to replace');
        $band = (string) strstr((string) strstr($html, 'id="song-words"'), '</section>', true);
        $this->assertStringNotContainsString(e($verse), $band, 'no template verse in the words band');
        // Five stanzas: three show, the other two fold under Show all 5 verses.
        $this->assertMatchesRegularExpression('#Verse 3</span>.*<details class="s-sg-more">\s*<summary class="btn-secondary">'.preg_quote(__('pages.song.all_verses', ['count' => 5]), '#').'</summary>.*Verse 4</span>.*Verse 5</span>.*</details>#s', $html);
        $this->assertSame(1, substr_count($html, '<details class="s-sg-more">'));
    }

    public function test_full_words_with_quotes_and_ampersands_read_as_written_never_double_escaped(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->write("The words mean what they say[^three].\n", "The words mean what they say[^three].\n\n### The full words\n\n**Verse 1**\nSays he, \"You're a Black Baller,\nGreensleeves was all my joy, &c.\n");
        $story = SongStories::story('test-song');
        $this->assertSame([['sung' => 'Says he, "You\'re a Black Baller,'], ['sung' => 'Greensleeves was all my joy, &c.']], $story['fullWords']['stanzas'][0]['lines'], 'plain text, its entities read back');
        File::copy($this->dir.'/test-song.md', $this->dir.'/amazing-grace.md');
        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertStringContainsString('Says he, &quot;You&#039;re a Black Baller,', $html, 'escaped once, as the page prints it');
        $this->assertStringContainsString('all my joy, &amp;c.', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
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

    public function test_a_song_page_shows_its_story_whole_and_a_song_without_one_keeps_its_about(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        File::copy($this->dir.'/test-song.md', $this->dir.'/amazing-grace.md');
        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();

        // Its own title and description, as its searches were written for.
        // Its <title> leads with the harmony intent, the longest form that fits (SongPage::pageTitle); the story's heading is its own.
        $this->assertStringContainsString('<title>Amazing Grace: Harmony Parts, Lyrics and Meaning</title>', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Amazing Grace: Harmony Parts, Lyrics and Meaning">', $html);
        $this->assertSame('Are You Sleeping (Frère Jacques): Harmony Parts and Lyrics', SongPage::pageTitle('Are You Sleeping (Frère Jacques)'));
        $this->assertSame('Now Is the Month of Maying: Harmony Parts and Lyrics', SongPage::pageTitle('Now Is the Month of Maying'));
        $this->assertSame('It Came Upon the Midnight Clear, Again Harmony Parts', SongPage::pageTitle('It Came Upon the Midnight Clear, Again'));
        $this->assertSame('Row, Row, Row Your Boat Round: Learn Every Part', SongPage::pageTitle('Row, Row, Row Your Boat', true), 'a round says round');
        $this->assertStringContainsString('<meta name="description" content="Written down in 1816 for the tests, and sung by nobody since.">', $html);
        $this->assertStringContainsString('A song made for the tests, with its sources and questions.', $html, 'the lead under the heading');
        // Each section it has, by its heading's id, listed in its contents; the timeline under where it comes from.
        foreach (['where-it-comes-from', 'what-the-words-mean', 'how-it-is-sung', 'did-you-know', 'singing-it', 'questions'] as $id) {
            $this->assertStringContainsString('<section id="'.$id.'"', $html, $id);
            $this->assertStringContainsString('<a href="#'.$id.'">', $html, $id.' in the contents');
        }
        $this->assertStringNotContainsString('<section id="sing-next"', $html, 'its Sing next is the page\'s More songs');
        $this->assertMatchesRegularExpression('#<ol class="s-sst-tl">.*1816.*written down.*1818.*printed#s', $html);
        // Every source numbered and its own id, as the citations link them; the questions answered on the page and as FAQ data.
        $story = SongStories::story('amazing-grace');
        $this->assertNotNull($story);
        foreach ($story['sources'] as $source) {
            $this->assertStringContainsString('<li id="source-'.$source['n'].'">', $html);
        }
        $this->assertStringContainsString('<h4>Who wrote it?</h4>', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        // Every verse, one sung line a line.
        $this->assertMatchesRegularExpression('#<div id="song-words" class="s-sg-lyrics">\s*<p>\s*<span class="s-sg-line">\s*<span class="s-sg-sung" translate="no"(?: lang="en")?>Amazing grace[^<]*</span>\s*</span>\s*<span class="s-sg-line">#', $html);

        // A song with no story keeps its About, and the page's composition data alone.
        File::delete($this->dir.'/amazing-grace.md');
        $plain = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="s-wrap s-sec2 s-sst"', $plain);
        $this->assertStringNotContainsString('"@type":"FAQPage"', $plain);
        $this->assertStringContainsString(__('site.song_part.about', ['song' => 'Amazing Grace']), $plain);
    }

    public function test_each_part_says_whose_voice_it_is_and_asks_for_one_only_behind_the_lend_voice_flag(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        // No real voices here: each part says so, and with the flag missing (or off) nothing asks.
        $this->assertSame(4, substr_count($html, '<span>'.e(__('pages.song.voice_generated')).'</span>'));
        $this->assertStringNotContainsString('data-be-voice', $html);
        $this->assertStringNotContainsString('data-be-voice', (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent());
    }

    public function test_with_lend_voice_on_a_part_with_no_real_voice_asks_to_be_sung_on_the_song_and_part_pages(): void
    {
        config(['flags.lend_voice' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('lend_voice', 'on', null, 'Lend your voice');
        app(SyncOfficialTemplates::class)->handle();

        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $part) {
            $this->assertStringContainsString('data-be-voice="'.$part.'">'.e(__('pages.song.be_voice', ['part' => $part])).'</a>', $html);
        }
        // Its one true note: may be chosen, never promised.
        $this->assertStringContainsString(e(__('pages.song.be_voice_note')), $html);
        $this->assertStringContainsString('we may choose your part', (string) __('pages.song.be_voice_note'));
        $this->assertStringContainsString('data-be-voice="alto"', (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent());
    }

    public function test_a_song_page_says_its_key_and_tempo_in_one_quiet_line_and_its_schema_names_the_key(): void
    {
        // The SEO audit (2026-11-13): "original key", "what key". The song file's opening key and tempo, as much as it has.
        $this->assertSame('Key of G major · about 76 beats a minute', SongPage::keyTempo('G', 76.4));
        $this->assertSame('Key of E♭ minor', SongPage::keyTempo('Ebm', 0));
        $this->assertSame('About 60 beats a minute', SongPage::keyTempo('', 60));
        $this->assertNull(SongPage::keyTempo('H', 0));
        $this->assertSame('Key of D major · about 64 beats a minute · changes time', SongPage::keyTempo('D', 64, false, true));

        app(SyncOfficialTemplates::class)->handle();
        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<p class="s-sg-keyline">Key of [A-G][♭♯]? (major|minor) · about \d+ beats a minute</p>#u', $html);
        $this->assertMatchesRegularExpression('#"musicalKey":"[A-G][^"]* (major|minor)"#u', $html);
    }

    public function test_a_whole_mix_credits_each_real_voice_else_says_the_voices_are_synthesized(): void
    {
        // One player on the page (the song page review, option C): no second, native player beside the hero's.
        app(SyncOfficialTemplates::class)->handle();
        $this->assertStringNotContainsString('s-sg-mix', (string) $this->get('/songs/amazing-grace')->assertOk()->getContent());

        $cards = [['key' => 'soprano', 'name' => 'Soprano'], ['key' => 'alto', 'name' => 'Alto']];
        $none = ['soprano' => ['realVoice' => false, 'credit' => null, 'creditHandle' => null], 'alto' => ['realVoice' => false, 'credit' => null, 'creditHandle' => null]];
        $this->assertSame('Synthesized voices', SongPage::mixCredit($cards, $none));
        $alto = ['alto' => ['realVoice' => true, 'credit' => 'Rosa', 'creditHandle' => 'rosa']] + $none;
        $this->assertSame('Alto sung by Rosa; the other parts synthesized', SongPage::mixCredit($cards, $alto));
    }

    public function test_a_song_not_in_the_pages_language_shows_each_lines_script_and_meaning_and_a_long_one_can_hide_them(): void
    {
        // The shape backend and the courses lead give ($song['lines']): a list per stanza of {sung, script?, scriptLang?,
        // meaning?}, the meaning in the page's language.
        $given = [[
            ['sung' => 'Tzur mishelo achalnu', 'script' => 'צוּר מִשֶּׁלּוֹ אָכַלְנוּ', 'scriptLang' => 'he', 'meaning' => 'The Rock whose food we have eaten'],
            ['sung' => 'barchu emunai', 'script' => 'בָּרְכוּ אֱמוּנַי', 'scriptLang' => 'he', 'meaning' => 'bless him, my faithful friends'],
            ['meaning' => 'a line with no sung words is left out'],
        ]];
        $lines = SongPage::lines($given, [['as sung']]);
        $this->assertCount(2, $lines[0]);
        $this->assertSame([[['sung' => 'as sung']]], SongPage::lines(null, [['as sung']]), 'no line by line words yet: the sung lines');
        $this->assertFalse(SongPage::meaningToggle($lines), 'a short song shows its meanings, no button');

        $html = (string) $this->view('public._song-words', ['lines' => $lines, 'toggle' => SongPage::meaningToggle($lines)]);
        $this->assertMatchesRegularExpression('#<span class="s-sg-script" translate="no"\s+lang="he"\s+dir="auto">צוּר מִשֶּׁלּוֹ אָכַלְנוּ</span>\s*<span class="s-sg-sung" translate="no">Tzur mishelo achalnu</span>\s*<span class="s-sg-meaning">The Rock whose food we have eaten</span>#u', $html);
        $this->assertStringNotContainsString('data-meaning-toggle', $html);

        // A long song (more than 16 sung lines) with meanings gets the button, shown as hiding them: they show without the script.
        $long = SongPage::lines([array_fill(0, SongPage::MEANING_LINES + 1, $given[0][0])], []);
        $this->assertTrue(SongPage::meaningToggle($long));
        $this->assertStringContainsString('data-meaning-toggle aria-expanded="true"', (string) $this->view('public._song-words', ['lines' => $long, 'toggle' => true]));
    }

    public function test_a_storys_pictures_show_where_it_places_them_with_every_credit_and_never_cropped(): void
    {
        // The shape backend gives (story['images'] keyed by number, story['hero']), placed by <!--figure:n--> on its own line.
        $story = SongStories::story('test-song');
        $this->assertNotNull($story);
        $image = ['src' => '/media/test-song/1-960.webp', 'srcset' => '/media/test-song/1-480.webp 480w, /media/test-song/1-960.webp 960w', 'width' => 960, 'height' => 1240,
            'alt' => 'The first printing\'s title page', 'caption' => 'The title page of the first printing.', 'date' => '1816', 'library' => 'Library of Congress', 'callNumber' => 'M1.A1',
            'credit' => null, 'licence' => 'PD-old', 'licenceName' => 'Public domain', 'licenceUrl' => null, 'sourceUrl' => 'https://www.loc.gov/item/example/'];
        $story['images'] = ['1' => $image, '2' => ['src' => '/media/test-song/2-960.webp', 'width' => 960, 'height' => 640, 'alt' => 'A church', 'licenceName' => 'CC BY 4.0', 'licenceUrl' => 'https://creativecommons.org/licenses/by/4.0/', 'credit' => 'Ann Photographer']];
        $story['hero'] = '1';
        $story['opening'] .= "<!--figure:2-->\n<!--figure:9-->\n";

        $html = (string) $this->view('public._song-story', ['story' => $story, 'song' => ['title' => 'Test Song'], 'quoteN' => null]);
        // Its own size, lazy (the story is below the fold), with its caption, date, library and call number, licence and original.
        $this->assertMatchesRegularExpression('#<img src="/media/test-song/1-960.webp"\s+srcset="[^"]+"\s+sizes="[^"]+"\s+width="960" height="1240" alt="The first printing&\#039;s title page"\s+loading="lazy"\s+decoding="async">#', $html);
        $this->assertStringContainsString('1816 · Library of Congress, M1.A1', $html);
        $this->assertStringContainsString('<a href="https://www.loc.gov/item/example/" class="s-lk">'.e(__('pages.song.story.original')).'</a>', $html);
        // A placed picture with a credit and a licence link; a missing one draws nothing.
        $this->assertStringContainsString('Ann Photographer', $html);
        $this->assertStringContainsString('<a href="https://creativecommons.org/licenses/by/4.0/" class="s-lk" rel="license">CC BY 4.0</a>', $html);
        $this->assertSame(2, substr_count($html, '<figure class="s-fig">'));
        $this->assertStringNotContainsString('<!--figure:', $html);
    }
}
