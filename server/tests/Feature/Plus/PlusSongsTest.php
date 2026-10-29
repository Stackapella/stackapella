<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_a_plus_song_is_for_plus_and_a_free_one_for_anyone(): void
    {
        $this->assertMatchesContract($this->getJson('/api/templates/silent-night'), 'templates.show', 403);
        $this->getJson('/api/templates/amazing-grace')->assertOk()->assertJsonPath('data.grant', 'free');
        $this->assertMatchesContract($this->getJson('/api/templates/nope'), 'templates.show', 404);

        $user = $this->member();
        $this->subscribe($user);
        $this->actingAs($this->fresh($user));

        $song = $this->getJson('/api/templates/silent-night');
        $this->assertMatchesContract($song, 'templates.show', 200);
        $song->assertJsonPath('data.grant', 'plus')->assertJsonPath('data.template.id', 'silent-night');
        $this->assertNotEmpty($song->json('data.template.parts'));
    }

    public function test_hillel_the_editor_and_an_assigned_beta_singer_open_a_plus_song_without_plus(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $admin = $this->getJson('/api/templates/silent-night');
        $this->assertMatchesContract($admin, 'templates.show', 200);
        $admin->assertJsonPath('data.grant', 'admin');
        $this->actingAs(User::factory()->editor()->create());
        $this->getJson('/api/templates/danny-boy')->assertOk()->assertJsonPath('data.grant', 'admin');

        // A beta singer: the song they're assigned, not the others; nothing once revoked.
        $singer = BetaSinger::factory()->accepted($user = User::factory()->create())->create();
        BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'template_id' => Template::query()->where('slug', 'silent-night')->value('id')]);
        $this->actingAs($user);
        $this->getJson('/api/templates/silent-night')->assertOk()->assertJsonPath('data.grant', 'beta');
        $this->getJson('/api/templates/danny-boy')->assertForbidden();
        $singer->forceFill(['revoked_at' => now()])->save();
        $this->getJson('/api/templates/silent-night')->assertForbidden();
    }

    public function test_the_song_of_the_month_is_for_anyone_and_kept_by_whoever_started_it(): void
    {
        Template::query()->where('slug', 'silent-night')->update(['free_from' => now()->startOfMonth(), 'free_until' => now()->endOfMonth()]);

        $signedOut = $this->getJson('/api/templates/silent-night');
        $signedOut->assertOk()->assertJsonPath('data.grant', 'month');
        $this->assertNotNull($signedOut->json('data.freeUntil'));

        $this->owner();
        $this->saveProject((string) Str::uuid(), 0, ['template' => ['id' => 'silent-night', 'version' => 1]])->assertOk();

        $this->travel(2)->months();
        $this->getJson('/api/templates/silent-night')->assertOk()->assertJsonPath('data.grant', 'kept');
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
