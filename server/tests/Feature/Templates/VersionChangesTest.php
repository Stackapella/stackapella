<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Actions\Templates\VersionChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class VersionChangesTest extends TestCase
{
    use RefreshDatabase;

    private string $old;

    private string $new;

    /** @var array<string, mixed> */
    private array $song;

    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('framework/testing/versions-'.uniqid());
        $this->old = "{$root}/old";
        $this->new = "{$root}/new";
        // A small song of its own, so a content pin reshaping a real song never breaks this test.
        $notes = static fn (int $pitch): array => ['type' => 'notes', 'notes' => [
            ['beat' => 0, 'length' => 2, 'pitch' => $pitch, 'lyric' => 'Sing'],
            ['beat' => 2, 'length' => 1, 'pitch' => $pitch + 2, 'lyric' => 'a'],
            ['beat' => 3, 'length' => 1, 'pitch' => $pitch + 4, 'lyric' => 'song'],
        ]];
        $this->song = [
            'id' => 'a-song', 'version' => 3, 'reviewed' => true, 'title' => 'A Song', 'description' => 'A test song.',
            'tempo' => 90, 'key' => 'C', 'timeSignature' => '4/4', 'pickupBeats' => 0,
            'sections' => [['name' => 'Verse 1', 'startBeat' => 0, 'endBeat' => 4, 'phrases' => [[0, 4]]]],
            'parts' => [
                ['key' => 'soprano', 'melody' => true, 'range' => ['low' => 60, 'high' => 79], 'guide' => $notes(72)],
                ['key' => 'alto', 'melody' => false, 'range' => ['low' => 55, 'high' => 74], 'guide' => $notes(64)],
            ],
        ];
        $this->writeSong($this->old, 'library', $this->song);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->old));

        parent::tearDown();
    }

    public function test_the_same_song_passes(): void
    {
        $this->writeSong($this->new, 'library', $this->song);

        $this->assertSame([], app(VersionChanges::class)->handle($this->old, $this->new));
        $this->artisan('app:templates:versions', ['old' => $this->old, 'new' => $this->new])->assertSuccessful();
    }

    public function test_changed_notes_at_the_same_version_are_refused(): void
    {
        $changed = $this->song;
        $changed['parts'][0]['guide']['notes'][0]['pitch'] += 1;
        $this->writeSong($this->new, 'library', $changed);

        $version = $this->song['version'];
        $this->assertSame(["templates/library/a-song.json: its notes changed at version {$version} since the pinned content; give it version ".($version + 1)], app(VersionChanges::class)->handle($this->old, $this->new));
        $this->artisan('app:templates:versions', ['old' => $this->old, 'new' => $this->new])->assertFailed();
    }

    public function test_a_breath_or_a_section_change_needs_a_version_too(): void
    {
        $breath = $this->song;
        $breath['parts'][1]['guide']['notes'][2]['br'] = true;
        $this->writeSong($this->new, 'library', $breath);
        $this->assertCount(1, app(VersionChanges::class)->handle($this->old, $this->new));

        $sections = $this->song;
        $sections['sections'][0]['name'] = 'Another name';
        $this->writeSong($this->new, 'library', $sections);
        $this->assertCount(1, app(VersionChanges::class)->handle($this->old, $this->new));
    }

    public function test_changed_notes_with_a_new_version_pass(): void
    {
        $changed = $this->song;
        $changed['parts'][0]['guide']['notes'][0]['pitch'] += 1;
        $changed['version'] += 1;
        $this->writeSong($this->new, 'library', $changed);

        $this->assertSame([], app(VersionChanges::class)->handle($this->old, $this->new));
    }

    public function test_the_words_around_the_music_may_change_in_place(): void
    {
        $this->writeSong($this->new, 'library', [...$this->song, 'description' => 'Another description.', 'reviewed' => ! $this->song['reviewed'], 'title' => 'Another title']);

        $this->assertSame([], app(VersionChanges::class)->handle($this->old, $this->new));
    }

    public function test_a_new_song_and_a_song_moving_folder_are_compared_by_id(): void
    {
        $changed = $this->song;
        $changed['parts'][0]['guide']['notes'][0]['length'] += 1;
        // Moved from the library to the free songs, notes changed, same version: still refused.
        $this->writeSong($this->new, 'free', $changed);
        $this->writeSong($this->new, 'library', [...$this->song, 'id' => 'a-new-song', 'version' => 1]);

        $this->assertSame(['templates/free/a-song.json'], array_map(static fn (string $line): string => strstr($line, ':', true) ?: $line, app(VersionChanges::class)->handle($this->old, $this->new)));
    }

    public function test_a_version_going_down_is_refused(): void
    {
        $this->writeSong($this->new, 'library', [...$this->song, 'version' => $this->song['version'] - 1]);

        $this->assertStringContainsString('below the pinned version', app(VersionChanges::class)->handle($this->old, $this->new)[0]);
    }

    public function test_a_tree_with_no_songs_is_a_wrong_folder(): void
    {
        File::ensureDirectoryExists($this->new);

        $this->assertStringStartsWith('no songs in', app(VersionChanges::class)->handle($this->old, $this->new)[0]);
        $this->artisan('app:templates:versions', ['old' => $this->old, 'new' => $this->new.'/missing'])->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $song
     */
    private function writeSong(string $root, string $folder, array $song): void
    {
        File::ensureDirectoryExists("{$root}/templates/{$folder}");
        File::put("{$root}/templates/{$folder}/".$song['id'].'.json', (string) json_encode($song));
        // A folder's list of songs is never a song.
        File::put("{$root}/templates/{$folder}/index.json", '[]');
    }
}
