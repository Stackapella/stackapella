<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Pages\SongParts;
use App\Support\Templates\Catalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hillel, 2026-11-08: "Yes, play for guests". Signed out, templates.show serves an open library song
 * (Catalogue::isOpen: live, reviewed, public domain), its current version only, under the daily cap a
 * network has, so its public part page plays its part. Every other library song stays an account's.
 */
final class GuestOpenSongsTest extends TestCase
{
    use RefreshDatabase;

    private string $open;

    private string $closed;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();

        // Two public-domain library songs the app doesn't bundle: one reviewed (open), one not (waiting for its Monday).
        $songs = Template::query()->listed()->with('currentVersion')->where('access', 'plus')->whereNotNull('current_version_id')->orderBy('slug')->get()
            ->filter(fn (Template $t): bool => SongParts::publicDomain((array) $t->currentVersion?->data))->values();
        [$open, $closed] = [$songs[0], $songs[1]];
        TemplateVersion::query()->whereKey($open->current_version_id)->update(['reviewed' => true]);
        TemplateVersion::query()->whereKey($closed->current_version_id)->update(['reviewed' => false]);
        $this->open = $open->slug;
        $this->closed = $closed->slug;
    }

    public function test_a_guest_plays_an_open_song_but_never_one_waiting_to_open(): void
    {
        $this->assertTrue(Catalogue::isOpen(Template::query()->with('currentVersion')->where('slug', $this->open)->firstOrFail()));

        $song = $this->getJson('/api/templates/'.$this->open);
        $this->assertMatchesContract($song, 'templates.show', 200);
        $song->assertJsonPath('data.grant', 'open')->assertJsonPath('data.template.id', $this->open);
        $this->assertNotEmpty($song->json('data.template.parts'));
        // Kept by the guest's own browser and checked again by its ETag; an account's copy is never kept.
        $this->assertSame(['no-cache', 'private'], self::directives($song->headers->get('Cache-Control')));

        // Unchanged: a 304, cacheable the same way.
        $etag = (string) $song->headers->get('ETag');
        $this->assertNotSame('', $etag);
        $this->getJson('/api/templates/'.$this->open, ['If-None-Match' => $etag])->assertStatus(304);

        // Not reviewed yet: an account's, as before.
        $this->assertMatchesContract($this->getJson('/api/templates/'.$this->closed), 'templates.show', 403);
        $this->getJson('/api/templates/'.$this->closed)->assertJsonPath('code', 'account_required');

        // Withdrawn, or no longer public domain: closed again.
        Template::query()->where('slug', $this->open)->update(['withdrawn_at' => now()]);
        $this->getJson('/api/templates/'.$this->open)->assertForbidden()->assertJsonPath('code', 'account_required');
    }

    public function test_a_guest_has_the_current_version_only(): void
    {
        $template = Template::query()->where('slug', $this->open)->firstOrFail();
        $current = TemplateVersion::query()->findOrFail($template->current_version_id);
        // An older version of the same song, never reviewed.
        TemplateVersion::factory()->create(['template_id' => $template->id, 'version' => $current->version + 50, 'reviewed' => false, 'data' => $current->data]);

        $this->getJson('/api/templates/'.$this->open.'?version='.$current->version)->assertOk()->assertJsonPath('data.grant', 'open');
        $this->getJson('/api/templates/'.$this->open.'?version='.($current->version + 50))->assertForbidden()->assertJsonPath('code', 'account_required');

        // Signed in, any version, as before, and never kept.
        $this->actingAs(User::factory()->create());
        $this->assertSame(['no-store', 'private'], self::directives($this->getJson('/api/templates/'.$this->open)->headers->get('Cache-Control')));
        $this->getJson('/api/templates/'.$this->open.'?version='.($current->version + 50))->assertOk()->assertJsonPath('data.grant', 'account');
    }

    /** @return list<string> */
    private static function directives(?string $header): array
    {
        $parts = array_map('trim', explode(',', (string) $header));
        sort($parts);

        return $parts;
    }

    public function test_a_network_has_a_daily_cap_and_the_same_song_again_doesnt_count(): void
    {
        config(['limits.plus-downloads.account.max' => 1]);
        $other = Template::query()->with('currentVersion')->listed()->where('access', 'plus')->whereNotIn('slug', [$this->open, $this->closed])->whereNotNull('current_version_id')->get()
            ->first(fn (Template $t): bool => SongParts::publicDomain((array) $t->currentVersion?->data)) ?? throw new \RuntimeException('no third song');
        TemplateVersion::query()->whereKey($other->current_version_id)->update(['reviewed' => true]);

        $this->getJson('/api/templates/'.$this->open)->assertOk();
        $this->getJson('/api/templates/'.$this->open)->assertOk();
        $this->assertMatchesContract($this->getJson('/api/templates/'.$other->slug), 'templates.show', 429);
    }
}
