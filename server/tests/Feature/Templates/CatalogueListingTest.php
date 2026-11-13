<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Templates\Catalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The library at every app start (templates.catalogue): each song's fixed part is kept under the catalogue's fingerprint
 * (Catalogue::listing), so a request reads no song's notes, while its voices and season are read live. The list from
 * the cache is the list built afresh, a new version shows at once, and a new voice at once.
 */
final class CatalogueListingTest extends TestCase
{
    use RefreshDatabase;

    private function song(string $slug, TemplateAccess $access): Template
    {
        $template = Template::factory()->create(['slug' => $slug, 'title' => ucfirst($slug), 'access' => $access]);
        $this->version($template);

        return $template->refresh();
    }

    private function version(Template $template, string $description = 'A song.'): void
    {
        $data = [...(array) TemplateVersion::factory()->make()->data, 'description' => $description, 'voicings' => ['2' => ['soprano', 'alto']]];
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'version' => (int) TemplateVersion::query()->where('template_id', $template->id)->max('version') + 1,
            'reviewed' => true, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data).$description)]);
        $template->forceFill(['current_version_id' => $version->id])->save();
    }

    /** @return array<string, array<string, mixed>> by id, as a new request answers it (a guest's: no one signed in) */
    private function list(?User $user = null): array
    {
        $this->app->forgetScopedInstances();
        if ($user === null) {
            $this->app['auth']->forgetGuards();
        }
        $response = $user !== null ? $this->actingAs($user)->getJson('/api/templates') : $this->getJson('/api/templates');
        $this->assertMatchesContract($response->assertOk(), 'templates.catalogue', 200);
        $songs = [];
        foreach ((array) $response->json('data') as $song) {
            $songs[(string) $song['id']] = (array) $song;
        }

        return $songs;
    }

    /**
     * @param  array<string, array<string, mixed>>  $songs
     * @return array<string, mixed>
     */
    private static function part(array $songs, string $id, string $key): array
    {
        foreach ((array) ($songs[$id]['parts'] ?? []) as $part) {
            if (is_array($part) && ($part['key'] ?? null) === $key) {
                return $part;
            }
        }
        self::fail("{$id} has no {$key}");
    }

    public function test_the_kept_list_is_the_list_built_afresh_and_changes_show_at_once(): void
    {
        $free = $this->song('free-song', TemplateAccess::Free);
        $plus = $this->song('plus-song', TemplateAccess::Plus);
        $user = User::factory()->create();

        $fresh = $this->list($user);
        $this->assertNotSame([], Cache::get('catalogue.listing.'.app(Catalogue::class)->fingerprint().'.'.(string) config('app.build')), 'kept');
        $this->assertSame($fresh, $this->list($user), 'from the cache, the same');
        $this->assertSame(['2' => ['soprano', 'alto']], $fresh['free-song']['voicings'], 'its voicings, as built');
        $this->assertArrayNotHasKey('_versionId', $fresh['free-song']);

        // A new version: its words at once.
        $this->version($plus, 'Its new words.');
        $this->assertSame('Its new words.', $this->list($user)['plus-song']['description']);

        // A new voice: at once, for whoever may start the song; a guest has the free song's only.
        $part = (string) (((array) $plus->refresh()->currentVersion?->data)['parts'][0]['key'] ?? 'soprano');
        TemplateGuide::factory()->create(['template_version_id' => $plus->current_version_id, 'part_key' => $part]);
        TemplateGuide::factory()->create(['template_version_id' => $free->current_version_id, 'part_key' => $part]);
        $signedIn = $this->list($user);
        $this->assertNotNull(self::part($signedIn, 'plus-song', $part)['guide']);
        $this->assertTrue(self::part($signedIn, 'plus-song', $part)['hasRealGuide']);
        $guest = $this->list();
        $this->assertNull(self::part($guest, 'plus-song', $part)['guide'], 'a guest can\'t start it');
        $this->assertTrue(self::part($guest, 'plus-song', $part)['hasRealGuide'], 'but sees a real voice sings it');
        $this->assertNotNull(self::part($guest, 'free-song', $part)['guide']);
    }

    public function test_a_request_reads_no_songs_notes_once_the_list_is_kept(): void
    {
        foreach (range(1, 6) as $n) {
            $this->song("song-{$n}", TemplateAccess::Plus);
        }
        $this->list();

        $read = [];
        DB::listen(function ($query) use (&$read): void {
            if (str_contains($query->sql, 'from `template_versions`')) {
                $read[] = $query->sql;
            }
        });
        $this->assertCount(6, $this->list());
        $this->assertSame([], $read, 'no version read');
    }
}
