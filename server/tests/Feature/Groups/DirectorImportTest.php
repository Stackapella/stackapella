<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Models\GroupSong;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A director makes tracks from a score (docs/score-import-plan.md, section 4; frontend2's slice C, c8bbcf2d4), on the
 * real server with the exact bodies the app sends: templates.save with the confirmed sentence (stores/templates.js's
 * first save), then groups.songs.create into the group they direct (components/create/director-import.js). Behind
 * sheet_import's "directors" value: closed with the switch off, and to anyone who directs no group.
 */
final class DirectorImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The app's first save of a song from a score: what stores/templates.js sends.
     *
     * @return TestResponse<JsonResponse>
     */
    private function save(string $id, string $rights): TestResponse
    {
        $template = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);

        return $this->putJson('/api/templates/'.$id, [
            'changeId' => (string) Str::uuid(),
            'template' => [...$template, 'id' => $id, 'version' => 1, 'title' => 'Our Own Arrangement', 'reviewed' => false],
            'visibility' => 'private',
            'libraryOptIn' => false,
            'source' => 'sheet-music',
            'rightsConfirmed' => true,
            'rights' => $rights,
        ]);
    }

    private function director(): User
    {
        $director = User::factory()->create(['name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'email_verified_at' => now()])->fresh() ?? throw new \RuntimeException('no user');
        $this->actingAs($director);

        return $director;
    }

    private function group(string $name = 'Thursday Singers'): string
    {
        return (string) $this->postJson('/api/groups', ['name' => $name, 'adult' => true])->assertCreated()->json('data.id');
    }

    public function test_a_director_saves_a_score_with_its_sentence_and_shares_it_into_their_group(): void
    {
        app(Flags::class)->set('sheet_import', 'directors', null, 'Directors try score import');
        $ana = $this->director();
        $group = $this->group();
        $id = (string) Str::uuid();

        $saved = $this->save($id, 'own-arrangement');
        $this->assertMatchesContract($saved, 'templates.save', 200);
        $saved->assertJsonPath('data.imported', true)->assertJsonPath('data.visibility', 'private');
        $song = Template::query()->where('slug', $id)->firstOrFail();
        $this->assertSame(['Ana Ruiz <ana@example.com>', __('support.tracks.rights.own-arrangement'), 'sheet-music'], [$song->rights_confirmed_by, $song->rights_wording, $song->rights_source]);
        $this->assertSame($ana->id, $song->owner_id);

        // Then into the group, as director-import.js does: { templateId: <the saved song's id> }.
        $this->assertMatchesContract($this->postJson('/api/groups/'.$group.'/songs', ['templateId' => $id]), 'groups.songs.create', 200);
        $this->assertTrue(GroupSong::query()->where('template_id', $song->id)->exists());
    }

    public function test_it_is_refused_with_the_switch_off_and_to_anyone_who_directs_no_group(): void
    {
        $this->director();
        $this->group();

        // Off: no new song from a score, and nothing is kept.
        app(Flags::class)->set('sheet_import', 'off', null, 'Closed');
        $closed = (string) Str::uuid();
        $this->assertMatchesContract($this->save($closed, 'public-domain'), 'templates.save', 403);
        $this->assertFalse(Template::query()->where('slug', $closed)->exists());

        // On for directors: a singer who directs no group (only a member of one) is refused.
        app(Flags::class)->set('sheet_import', 'directors', null, 'Directors try score import');
        $token = (string) str($this->postJson('/api/groups/'.$this->group('Saturday Choir').'/link')->assertCreated()->json('data.url'))->afterLast('/choir/');
        $this->actingAs(User::factory()->create(['email_verified_at' => now()])->fresh() ?? throw new \RuntimeException('no user'));
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertSuccessful();
        $member = (string) Str::uuid();
        $this->assertMatchesContract($this->save($member, 'public-domain'), 'templates.save', 403);
        $this->assertFalse(Template::query()->where('slug', $member)->exists());
    }

    public function test_a_director_can_share_it_only_into_a_group_they_direct(): void
    {
        app(Flags::class)->set('sheet_import', 'directors', null, 'Directors try score import');
        // Someone else's group, which Ana joins as a singer.
        $this->actingAs(User::factory()->create(['email_verified_at' => now()])->fresh() ?? throw new \RuntimeException('no user'));
        $theirs = $this->group('Their Choir');
        $token = (string) str($this->postJson('/api/groups/'.$theirs.'/link')->assertCreated()->json('data.url'))->afterLast('/choir/');

        $ana = $this->director();
        $this->group();
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertSuccessful();
        $id = (string) Str::uuid();
        $this->save($id, 'public-domain')->assertOk();

        // The share fails, the song stays private and hers (the screen says so and offers Try again).
        $this->assertMatchesContract($this->postJson('/api/groups/'.$theirs.'/songs', ['templateId' => $id]), 'groups.songs.create', 403);
        $song = Template::query()->where('slug', $id)->firstOrFail();
        $this->assertFalse(GroupSong::query()->where('template_id', $song->id)->exists());
        $this->assertSame([$ana->id, 'private'], [$song->owner_id, $song->visibility]);
    }
}
