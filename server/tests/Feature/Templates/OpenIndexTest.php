<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Pages\SongsHub;
use App\Support\Templates\Catalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The open songs' index (Catalogue::openIndex): what the song pages pick by, kept in the cache under the catalogue's
 * fingerprint, so a song page never reads every song's notes (3.6 MB of them, most of its time) and any change to the
 * songs reads it afresh.
 */
final class OpenIndexTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $with  over the factory's data */
    private function song(string $slug, string $title, array $with = []): Template
    {
        $template = Template::factory()->create(['slug' => $slug, 'title' => $title, 'access' => TemplateAccess::Plus]);
        $this->version($template, $with);

        return $template->refresh();
    }

    /** @param  array<string, mixed>  $with */
    private function version(Template $template, array $with): void
    {
        $data = [...(array) TemplateVersion::factory()->make()->data, ...$with];
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'version' => (int) TemplateVersion::query()->where('template_id', $template->id)->max('version') + 1,
            'reviewed' => true, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data))]);
        $template->forceFill(['current_version_id' => $version->id])->save();
    }

    /** @return list<array{slug: string, title: string, genre: list<string>, difficulty: string}> as a new request reads it */
    private function index(): array
    {
        $this->app->forgetScopedInstances();

        return app(Catalogue::class)->openIndex();
    }

    public function test_it_lists_the_open_songs_in_title_order_and_reads_afresh_when_they_change(): void
    {
        $zebra = $this->song('zebra-song', 'Zebra Song', ['genre' => ['Folk'], 'difficulty' => 'easy']);
        $this->song('apple-song', 'Apple Song', ['genre' => ['Hymn'], 'difficulty' => 'medium']);

        $this->assertSame([
            ['slug' => 'apple-song', 'title' => 'Apple Song', 'genre' => ['Hymn'], 'difficulty' => 'medium'],
            ['slug' => 'zebra-song', 'title' => 'Zebra Song', 'genre' => ['Folk'], 'difficulty' => 'easy'],
        ], $this->index());

        // A new version: its genres and level.
        $this->version($zebra, ['genre' => ['Carol'], 'difficulty' => 'hard']);
        $this->assertSame(['Carol'], $this->index()[1]['genre']);

        // Unreviewed, then withdrawn: off the index at once.
        // (A version itself never changes; its reviewed flag is set outside the model.)
        $current = (int) $zebra->refresh()->current_version_id;
        DB::table('template_versions')->where('id', $current)->update(['reviewed' => false]);
        $this->assertSame(['apple-song'], array_column($this->index(), 'slug'));
        DB::table('template_versions')->where('id', $current)->update(['reviewed' => true]);
        $this->assertSame(['apple-song', 'zebra-song'], array_column($this->index(), 'slug'));
        $zebra->forceFill(['withdrawn_at' => now()])->save();
        $this->assertSame(['apple-song'], array_column($this->index(), 'slug'));
    }

    public function test_a_song_page_reads_only_its_own_version_and_its_more_songs(): void
    {
        foreach (range(1, 8) as $n) {
            $this->song("song-{$n}", "Song {$n}");
        }
        $this->get('/songs/song-1')->assertOk();

        $read = [];
        DB::listen(function ($query) use (&$read): void {
            if (str_contains($query->sql, 'from `template_versions`')) {
                $read[] = $query->sql;
            }
        });
        $this->app->forgetScopedInstances();
        $page = $this->get('/songs/song-1')->assertOk();

        $this->assertCount(3, $page->viewData('more'));
        foreach ($read as $sql) {
            $this->assertLessThanOrEqual(3, substr_count((string) preg_replace('/^.* in \(([^)]*)\).*$/s', '$1', $sql), ','), "a version read of more than its own and three: {$sql}");
        }
    }

    public function test_songs_lists_every_open_song_from_the_index_and_reads_only_its_sample_cards(): void
    {
        foreach (range(1, 12) as $n) {
            $this->song("song-{$n}", "Song {$n}", ['genre' => ['Folk']]);
        }
        $this->get('/songs')->assertOk();

        $read = [];
        DB::listen(function ($query) use (&$read): void {
            if (str_contains($query->sql, 'from `template_versions`')) {
                $read[] = $query->sql;
            }
        });
        $this->app->forgetScopedInstances();
        $page = $this->get('/songs')->assertOk();

        $this->assertSame(12, $page->viewData('count'));
        $this->assertCount(12, $page->viewData('index'));
        foreach ($read as $sql) {
            $this->assertLessThanOrEqual(SongsHub::PER_BAND - 1, substr_count((string) preg_replace('/^.* in \(([^)]*)\).*$/s', '$1', $sql), ','), "a version read of more than a band's samples: {$sql}");
        }
    }
}
