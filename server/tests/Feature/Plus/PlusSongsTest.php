<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CloudSave;
use Tests\Support\Plus;
use Tests\TestCase;

final class PlusSongsTest extends TestCase
{
    use CloudSave;
    use Plus;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SyncOfficialTemplates::class)->handle();
        $this->fakeStripe();
    }

    public function test_every_library_song_is_free_with_an_account_and_the_bundled_ones_for_anyone(): void
    {
        // Signed out: a song the app bundles, and an open one (GuestOpenSongsTest), never one waiting to
        // open (a free account opens them).
        $this->waiting('silent-night');
        $this->assertMatchesContract($this->getJson('/api/templates/silent-night'), 'templates.show', 403);
        $this->getJson('/api/templates/silent-night')->assertJsonPath('code', 'account_required');
        $this->getJson('/api/templates/amazing-grace')->assertOk()->assertJsonPath('data.grant', 'free');
        $this->assertMatchesContract($this->getJson('/api/templates/nope'), 'templates.show', 404);

        // Any account, on any plan (2026-11-07).
        $this->actingAs(User::factory()->create());
        $song = $this->getJson('/api/templates/silent-night');
        $this->assertMatchesContract($song, 'templates.show', 200);
        $song->assertJsonPath('data.grant', 'account')->assertJsonPath('data.template.id', 'silent-night')->assertJsonPath('data.freeUntil', null);
        $this->assertNotEmpty($song->json('data.template.parts'));
        $this->getJson('/api/templates/amazing-grace')->assertOk()->assertJsonPath('data.grant', 'free');
    }

    /** A song not reviewed yet (waiting for its Monday): never open to a guest. */
    private function waiting(string $slug): void
    {
        TemplateVersion::query()->whereKey(Template::query()->where('slug', $slug)->value('current_version_id'))->update(['reviewed' => false]);
    }

    public function test_hillel_and_the_editor_open_every_song_outside_the_daily_cap(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $admin = $this->getJson('/api/templates/silent-night');
        $this->assertMatchesContract($admin, 'templates.show', 200);
        $admin->assertJsonPath('data.grant', 'admin');
        $this->actingAs(User::factory()->editor()->create());
        $this->getJson('/api/templates/danny-boy')->assertOk()->assertJsonPath('data.grant', 'admin');
    }

    public function test_the_song_of_the_month_is_over(): void
    {
        // A song whose old free month is now: nothing about it differs any more.
        $this->waiting('silent-night');
        Template::query()->where('slug', 'silent-night')->update(['free_from' => now()->startOfMonth(), 'free_until' => now()->endOfMonth()]);
        $this->getJson('/api/templates/silent-night')->assertForbidden()->assertJsonPath('code', 'account_required');
        $this->getJson('/api/templates')->assertOk()->assertJsonPath('data.'.collect($this->getJson('/api/templates')->json('data'))->search(fn (array $s): bool => $s['id'] === 'silent-night').'.freeUntil', null);
    }

    public function test_an_unchanged_song_answers_304_and_the_daily_cap_holds(): void
    {
        $user = $this->member();
        $this->subscribe($user);
        $this->actingAs($this->fresh($user));

        $first = $this->getJson('/api/templates/silent-night');
        $etag = (string) $first->headers->get('ETag');
        $this->assertNotSame('', $etag);
        $this->getJson('/api/templates/silent-night', ['If-None-Match' => $etag])->assertStatus(304);

        config(['limits.plus-downloads.account.max' => 1]);
        // The same song again the same day doesn't count; another song is past the cap.
        $this->getJson('/api/templates/silent-night')->assertOk();
        $capped = $this->getJson('/api/templates/'.Template::query()->where('access', 'plus')->where('slug', '!=', 'silent-night')->value('slug'));
        $this->assertMatchesContract($capped, 'templates.show', 429);
    }
}
