<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\AuditLog;
use App\Models\Template;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A partner's song given by Admin (docs/partner-songs.md, gap 1): into the account as its own,
 * imported and private, with the partner's written rights record, under sheet import's guardrails
 * whether or not import is on.
 */
final class GiveSongTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private static function body(array $rights = []): array
    {
        $song = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);

        return ['song' => [...$song, 'id' => 'the-arrangers-own-id', 'version' => 1, 'title' => 'Shenandoah (our arrangement)', 'reviewed' => true],
            'rights' => ['confirmedBy' => 'Ana Ruiz, Music Director', 'confirmedAt' => '2026-11-01T10:00:00Z', 'wording' => 'I arranged it and the chorus may use it privately.', 'source' => 'Email, 1 November 2026', ...$rights]];
    }

    public function test_admin_gives_a_partners_song_that_stays_private_with_its_rights_record(): void
    {
        $director = User::factory()->create(['name' => 'Ana Ruiz']);
        $this->actingAsAdmin();

        $given = $this->postJson('/api/admin/people/'.$director->sqid().'/songs', self::body());
        $this->assertMatchesContract($given, 'admin.people.songs.create', 201);
        $given->assertJsonPath('data.visibility', 'private')->assertJsonPath('data.owner.name', 'Ana Ruiz')->assertJsonPath('data.rights.confirmedBy', 'Ana Ruiz, Music Director');

        $template = Template::query()->where('owner_id', $director->id)->sole();
        $this->assertTrue($template->isImported());
        $this->assertNotSame('the-arrangers-own-id', $template->slug);
        $this->assertSame(['partner', 'I arranged it and the chorus may use it privately.', 'Email, 1 November 2026'], [$template->rights_statement, $template->rights_wording, $template->rights_source]);
        $this->assertSame(1, AuditLog::query()->where('action', 'templates.given')->count());

        // Imported, so it can never be made public or put in the library, sheet import off or not.
        $this->actingAs($director);
        $this->patchJson('/api/templates/'.$template->slug, ['visibility' => 'unlisted'])->assertStatus(422)->assertJsonPath('code', 'imported_private');
        $this->patchJson('/api/templates/'.$template->slug, ['libraryOptIn' => true])->assertStatus(422)->assertJsonPath('code', 'imported_private');
        $this->assertSame('private', $template->refresh()->visibility);
    }

    public function test_a_free_director_shares_every_song_given_them_with_their_group_and_no_plan_is_needed(): void
    {
        // Stage 1 of the choir tracks (docs/choir-tracks-plan.md): we make the songs, give them to the
        // director's account, and they share them with their group. Nothing asks for Plus, and a
        // given song never counts toward the free plan's one private template.
        $director = User::factory()->create(['plan' => 'free']);
        $this->actingAsAdmin();
        $slugs = [];
        foreach (['Shenandoah (our arrangement)', 'The Water Is Wide (our arrangement)'] as $title) {
            $slugs[] = (string) $this->postJson('/api/admin/people/'.$director->sqid().'/songs', ['song' => [...self::body()['song'], 'title' => $title], 'rights' => self::body()['rights']])->assertCreated()->json('data.id');
        }
        $this->assertSame(0, app(Plans::class)->countedTemplates($director)->count());

        $this->actingAs($director->refresh());
        $group = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        foreach ($slugs as $slug) {
            $this->postJson('/api/groups/'.$group.'/songs', ['templateId' => $slug])->assertOk();
        }
        $this->assertSame([false, false], array_column((array) $this->getJson('/api/templates/mine')->assertOk()->json('data'), 'readOnly'));
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$group.'/link')->assertCreated()->json('data.url'), '/choir/');

        $this->actingAs(User::factory()->create(['plan' => 'free'])->refresh());
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        foreach ($slugs as $slug) {
            $this->getJson('/api/templates/'.$slug)->assertOk()->assertJsonPath('data.grant', 'group');
        }
    }

    public function test_their_own_copy_of_a_given_song_counts_toward_the_free_plan_and_the_given_song_never_does(): void
    {
        $director = User::factory()->create(['plan' => 'free']);
        $this->actingAsAdmin();
        $given = (string) $this->postJson('/api/admin/people/'.$director->sqid().'/songs', self::body())->assertCreated()->json('data.id');

        $this->actingAs($director->refresh());
        $data = (array) self::body()['song'];
        $copy = (string) Str::uuid();
        $this->putJson('/api/templates/'.$copy, ['changeId' => (string) Str::uuid(), 'copiedFrom' => $given, 'template' => [...$data, 'id' => $copy, 'version' => 1, 'title' => 'Shenandoah (my copy)']])
            ->assertOk()->assertJsonPath('data.imported', true);

        $theCopy = Template::query()->where('slug', $copy)->sole();
        $this->assertSame('partner', $theCopy->rights_statement);
        $this->assertFalse($theCopy->isGiven());
        $this->assertTrue(Template::query()->where('slug', $given)->sole()->isGiven());
        $plans = app(Plans::class);
        $this->assertSame([$theCopy->id], $plans->countedTemplates($director)->pluck('id')->all());
        $this->assertTrue(Plans::counts($theCopy));
        $this->assertFalse(Plans::counts(Template::query()->where('slug', $given)->sole()));
    }

    public function test_the_person_page_lists_the_songs_staff_gave_and_never_their_own_private_songs(): void
    {
        $director = User::factory()->create();
        $admin = User::factory()->admin()->create(['name' => 'Staff Member']);
        $this->actingAsAdmin($admin);
        foreach (['Shenandoah (our arrangement)', 'The Water Is Wide (our arrangement)'] as $title) {
            $this->postJson('/api/admin/people/'.$director->sqid().'/songs', ['song' => [...self::body()['song'], 'title' => $title], 'rights' => self::body()['rights']])->assertCreated();
        }
        [$older, $newer] = Template::query()->where('owner_id', $director->id)->orderBy('id')->get()->all();
        // Their own private song, and their own copy of a given song (it carries the rights record), never appear.
        Template::factory()->create(['owner_id' => $director->id, 'visibility' => 'private', 'title' => 'My own song']);
        Template::factory()->create(['owner_id' => $director->id, 'visibility' => 'private', 'title' => 'My copy of Shenandoah', 'imported_at' => now(), 'rights_statement' => 'partner']);
        // A song given to someone else isn't theirs.
        $this->postJson('/api/admin/people/'.User::factory()->create()->sqid().'/songs', self::body())->assertCreated();

        $page = $this->getJson('/api/admin/people/'.$director->sqid());
        $this->assertMatchesContract($page, 'admin.people.show', 200);
        $given = (array) $page->json('data.given');
        $this->assertSame([$newer->slug, $older->slug], array_column($given, 'id'));
        $this->assertSame(['The Water Is Wide (our arrangement)', 'Shenandoah (our arrangement)'], array_column($given, 'title'));
        $this->assertStringEndsWith('/t/'.$newer->slug, (string) $given[0]['url']);
        $this->assertSame(['id' => $admin->sqid(), 'name' => 'Staff Member'], $given[0]['givenBy']);
        $this->assertNotNull($given[0]['givenAt']);

        // Deleted from their account, it's gone from the list; the giver's account gone, givenBy is null.
        $older->forceFill(['deleted_at' => now()])->save();
        $this->assertSame([$newer->slug], array_column((array) $this->getJson('/api/admin/people/'.$director->sqid())->json('data.given'), 'id'));
        AuditLog::query()->where('action', 'templates.given')->update(['actor_id' => null]);
        $this->assertNull($this->getJson('/api/admin/people/'.$director->sqid())->json('data.given.0.givenBy'));
    }

    public function test_it_needs_the_whole_rights_record_a_confirmed_owner_and_an_admin(): void
    {
        $director = User::factory()->create();
        $admin = $this->actingAsAdmin();

        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$director->sqid().'/songs', self::body(['wording' => null])), 'admin.people.songs.create', 422);
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$unverified->sqid().'/songs', self::body()), 'admin.people.songs.create', 403);

        $this->actingAsEditor();
        $this->postJson('/api/admin/people/'.$director->sqid().'/songs', self::body())->assertForbidden();
        $this->assertSame(0, Template::query()->where('owner_id', $director->id)->count());
        $this->assertNotNull($admin->id);
    }
}
