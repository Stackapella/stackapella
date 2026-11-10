<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

final class CatalogueTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_anyone_sees_every_song_with_its_parts_but_never_the_notes(): void
    {
        $this->catalogue();

        $response = $this->getJson('/api/templates');

        $this->assertMatchesContract($response, 'templates.catalogue', 200);
        $this->assertSame(Template::query()->count(), count($response->json('data')));
        $this->assertStringNotContainsString('"notes"', (string) $response->getContent());
        $this->assertStringNotContainsString('"lyric"', (string) $response->getContent());
        $grace = collect($response->json('data'))->firstWhere('id', 'amazing-grace');
        $this->assertSame('free', $grace['access']);
        // How many can sing it, for the library card: the template's own voicings.
        $this->assertSame(json_decode((string) file_get_contents(dirname(base_path()).'/web/templates/amazing-grace.json'), true)['voicings'], $grace['voicings']);
        $this->assertTrue($grace['reviewed']);
        $this->assertSame(['soprano', 'alto', 'tenor', 'bass'], array_column($grace['parts'], 'key'));
    }

    public function test_real_voices_show_signed_out_for_the_bundled_songs_and_signed_in_for_every_song(): void
    {
        $grace = $this->catalogue();
        $silent = Template::query()->where('slug', 'silent-night')->firstOrFail();
        TemplateGuide::factory()->create(['template_version_id' => $grace->current_version_id, 'part_key' => 'alto']);
        TemplateGuide::factory()->create(['template_version_id' => $silent->current_version_id, 'part_key' => 'alto']);

        $songs = collect($this->getJson('/api/templates')->json('data'))->keyBy('id');
        $this->assertSame(['free', 'account'], [$songs['amazing-grace']['access'], $songs['silent-night']['access']]);
        $this->assertNotNull($songs['amazing-grace']['parts'][1]['guide']);
        $this->assertNull($songs['silent-night']['parts'][1]['guide'], 'Signed out, a song the app doesn\'t bundle never gives away its parts.');
        $this->assertNull($songs['silent-night']['freeUntil']);

        // Every library song is free with an account (2026-11-07): any plan.
        $this->actingAs(User::factory()->create());
        $response = $this->getJson('/api/templates');
        $this->assertMatchesContract($response, 'templates.catalogue', 200);
        $songs = collect($response->json('data'))->keyBy('id');
        $this->assertNotNull($songs['silent-night']['parts'][1]['guide']);
        $this->assertNull($songs['silent-night']['freeUntil']);
    }

    public function test_the_catalogue_doesnt_ask_more_as_the_library_grows(): void
    {
        $this->catalogue();
        $this->getJson('/api/templates')->assertOk();
    }
}
