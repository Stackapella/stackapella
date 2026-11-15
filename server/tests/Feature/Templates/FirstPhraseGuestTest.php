<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Flags;
use App\Support\Templates\Catalogue;
use App\Support\Templates\FirstPhrase;
use App\Support\Templates\TemplateRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hillel, 2026-11-15: "Sing the first phrase, then ask". Signed out, a live library song that isn't open
 * to guests whole (grant "open") answers its first phrase alone: grant "phrase", no voices, no boards,
 * its current version only. Withheld here, withdrawn, or an older version: still refused.
 */
final class FirstPhraseGuestTest extends TestCase
{
    use RefreshDatabase;

    private Template $song;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();

        // A library song the app doesn't bundle, its current version waiting for review: not open.
        $this->song = Template::query()->listed()->with('currentVersion')->where('access', 'plus')->whereNotNull('current_version_id')->orderBy('slug')->firstOrFail();
        TemplateVersion::query()->whereKey($this->song->current_version_id)->update(['reviewed' => false]);
        $this->song->refresh()->load('currentVersion');
        $this->assertFalse(Catalogue::isOpen($this->song));
    }

    public function test_a_guest_has_the_first_phrase_and_never_the_whole_song(): void
    {
        $whole = (array) $this->song->currentVersion?->data;
        $end = (float) $whole['sections'][0]['phrases'][0][1];

        $phrase = $this->getJson('/api/templates/'.$this->song->slug);
        $this->assertMatchesContract($phrase, 'templates.show', 200);
        $phrase->assertJsonPath('data.grant', 'phrase')->assertJsonPath('data.voices', [])->assertJsonPath('data.boards', false)
            ->assertJsonPath('data.template.id', $this->song->slug);

        $template = (array) $phrase->json('data.template');
        $this->assertEquals(FirstPhrase::of($whole), $template);
        $this->assertSame([], (new TemplateRules)->problems($template));
        $this->assertCount(1, $template['sections']);
        $notes = 0;
        foreach ($template['parts'] as $part) {
            foreach ($part['guide']['notes'] as $note) {
                $this->assertLessThanOrEqual($end + 1e-9, $note['beat'] + $note['length']);
                $notes++;
            }
        }
        $all = array_sum(array_map(static fn (array $part): int => count($part['guide']['notes']), $whole['parts']));
        $this->assertLessThan($all, $notes);

        // Kept by the guest's browser and revalidated, as an open song is.
        $parts = array_map('trim', explode(',', (string) $phrase->headers->get('Cache-Control')));
        sort($parts);
        $this->assertSame(['no-cache', 'private'], $parts);
        $etag = (string) $phrase->headers->get('ETag');
        $this->getJson('/api/templates/'.$this->song->slug, ['If-None-Match' => $etag])->assertStatus(304);

        // Signed in: the whole song, under an ETag of its own.
        $this->actingAs(User::factory()->create());
        $account = $this->getJson('/api/templates/'.$this->song->slug, ['If-None-Match' => $etag])->assertOk()->assertJsonPath('data.grant', 'account');
        $this->assertEquals($whole, $account->json('data.template'));
        $this->assertNotSame($etag, $account->headers->get('ETag'));
    }

    public function test_an_older_version_or_a_withdrawn_song_stays_an_accounts(): void
    {
        $current = (int) $this->song->currentVersion?->version;
        TemplateVersion::factory()->create(['template_id' => $this->song->id, 'version' => $current + 50, 'reviewed' => false, 'data' => $this->song->currentVersion?->data]);

        $older = $this->getJson('/api/templates/'.$this->song->slug.'?version='.($current + 50));
        $this->assertMatchesContract($older, 'templates.show', 403);
        $older->assertJsonPath('code', 'account_required');

        Template::query()->whereKey($this->song->id)->update(['withdrawn_at' => now()]);
        $this->getJson('/api/templates/'.$this->song->slug)->assertForbidden()->assertJsonPath('code', 'account_required');
    }

    public function test_a_song_with_no_phrase_stays_an_accounts(): void
    {
        $data = (array) $this->song->currentVersion?->data;
        $data['sections'] = [[...$data['sections'][0], 'phrases' => []]];
        $version = TemplateVersion::factory()->create(['template_id' => $this->song->id, 'version' => (int) $this->song->currentVersion?->version + 1, 'reviewed' => false, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data))]);
        $this->song->forceFill(['current_version_id' => $version->id])->save();

        $this->getJson('/api/templates/'.$this->song->slug)->assertForbidden()->assertJsonPath('code', 'account_required');
    }

    public function test_withheld_where_the_guest_is_comes_first(): void
    {
        config(['proxies.behind_app_platform' => true, 'selfhost.behind_cloudflare' => true]);
        // A song whose words are still protected in Mexico (life + 100), not open (unreviewed), not bundled.
        $template = Template::factory()->create(['slug' => 'words-song', 'title' => 'Words song', 'access' => TemplateAccess::Plus]);
        $data = [...(array) TemplateVersion::factory()->make()->data, 'id' => 'words-song',
            'makers' => [['name' => 'A Composer', 'role' => 'music', 'died' => 1850], ['name' => 'A Poet', 'role' => 'words', 'died' => 1960]],
            'firstPublished' => ['year' => 1900, 'country' => 'US']];
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => false, 'data' => $data, 'sha256' => hash('sha256', (string) json_encode($data))]);
        $template->forceFill(['current_version_id' => $version->id])->save();
        app(Flags::class)->set('geo_rights', 'on', null, 'test');

        $visitor = static fn (string $country): array => ['do-connecting-ip' => '172.70.1.2', 'cf-connecting-ip' => '198.51.100.7', 'cf-ipcountry' => $country];
        $this->withHeaders($visitor('MX'))->getJson('/api/templates/words-song')->assertStatus(451)->assertJsonPath('code', 'not_available_here');
        $this->withHeaders($visitor('US'))->getJson('/api/templates/words-song')->assertOk()->assertJsonPath('data.grant', 'phrase');
    }
}
