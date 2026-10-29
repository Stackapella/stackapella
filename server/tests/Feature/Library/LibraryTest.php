<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Actions\Users\DeleteAccount;
use App\Models\AuditLog;
use App\Models\Notice;
use App\Models\Project;
use App\Models\Report;
use App\Models\Template;
use App\Models\TemplatePartRange;
use App\Models\TemplateRating;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The template library (N3; contract/api.md, "The template library (N3)"): publishing with the
 * licensing declaration, review, search and filters, ratings and uses, remixes, reports and
 * moderation.
 */
final class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private const DECLARED = ['song' => 'public_domain', 'source' => 'Traditional', 'ownWork' => true];

    private function open(): void
    {
        app(Flags::class)->set('template_library', 'on', null, 'Opening the library');
    }

    /**
     * Someone with a public profile at a trust level (2 by default), 18 or over, signed in.
     */
    private function creator(string $handle, int $level = 2, bool $public = true): User
    {
        $user = User::factory()->create([
            'handle' => $handle,
            'name' => ucfirst($handle),
            'adult_confirmed_at' => now(),
            'public_profile_at' => $public ? now() : null,
            'trust_level_override' => $level,
        ])->fresh() ?? throw new \LogicException;
        $this->actingAs($user);

        return $user;
    }

    /**
     * A template of the signed-in person's, saved: Amazing Grace's JSON under a new id.
     *
     * @param  array<string, mixed>  $with  changes to the template JSON
     * @param  array<string, mixed>  $body  more of the request
     */
    private function saved(array $with = [], array $body = [], int $version = 1, ?string $id = null): string
    {
        $id ??= (string) Str::uuid();
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $this->putJson('/api/templates/'.$id, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $id, 'version' => $version, 'title' => 'Shall We Gather', 'reviewed' => true, ...$with], ...$body])->assertOk();

        return $id;
    }

    private function publish(string $id, array $declaration = self::DECLARED): TestResponse
    {
        return $this->putJson('/api/templates/'.$id.'/publication', $declaration);
    }

    /**
     * Published and approved: listed.
     *
     * @param  array<string, mixed>  $with
     */
    private function listed(array $with = []): string
    {
        $id = $this->saved($with);
        $this->publish($id)->assertOk();
        Template::query()->where('slug', $id)->update(['held_at' => null]);

        return $id;
    }

    public function test_nothing_shows_and_nothing_publishes_while_the_library_is_closed(): void
    {
        $this->creator('ana');
        $id = $this->saved([], ['libraryOptIn' => true]);

        $this->assertMatchesContract($this->getJson('/api/library'), 'library.list', 404);
        $closed = $this->publish($id);
        $this->assertMatchesContract($closed, 'templates.publish', 409);
        $closed->assertJsonPath('code', 'library_closed');
        $this->assertSame('private', Template::query()->where('slug', $id)->value('visibility'));
    }

    public function test_publishing_needs_an_adult_with_a_public_profile_trust_and_the_declaration(): void
    {
        $this->open();
        $newbie = $this->creator('newbie', 1);
        $id = $this->saved();
        $this->assertMatchesContract($this->publish($id), 'templates.publish', 403);

        $teen = User::factory()->create(['handle' => 'teen', 'public_profile_at' => now(), 'trust_level_override' => 2])->fresh() ?? throw new \LogicException;
        $this->actingAs($teen);
        $theirs = $this->saved();
        $refused = $this->publish($theirs);
        $this->assertMatchesContract($refused, 'templates.publish', 403);
        $refused->assertJsonPath('code', 'adult_required');

        $this->creator('ana', public: false);
        $hers = $this->saved();
        $this->assertMatchesContract($this->publish($hers), 'templates.publish', 409);
        $ana = User::query()->where('handle', 'ana')->sole();
        $ana->forceFill(['public_profile_at' => now()])->save();
        $this->actingAs($ana->fresh() ?? $ana);
        $this->assertMatchesContract($this->publish($hers, ['song' => 'someone_else', 'ownWork' => false]), 'templates.publish', 422);
        $this->assertMatchesContract($this->putJson('/api/templates/'.Str::uuid().'/publication', self::DECLARED), 'templates.publish', 404);
        $this->assertMatchesContract($this->publish($id), 'templates.publish', 404);
        $this->assertNotNull($newbie);

        // Published: the declaration is kept with it, and the first two wait for review.
        $published = $this->publish($hers);
        $this->assertMatchesContract($published, 'templates.publish', 200);
        $published->assertJsonPath('data.visibility', 'public')->assertJsonPath('data.library.status', 'held')->assertJsonPath('data.library.creator.handle', 'ana');
        $template = Template::query()->with('currentVersion')->where('slug', $hers)->sole();
        $this->assertSame('public_domain', $template->declaration['song'] ?? null);
        $this->assertSame('Traditional', $template->declaration['source'] ?? null);
        $this->assertTrue(AuditLog::query()->where('action', 'library.published')->exists());
        $this->getJson('/api/library')->assertOk()->assertJsonCount(0, 'data');

        // A level-3 creator's never wait.
        $this->creator('kim', 3);
        $this->publish($this->saved())->assertOk()->assertJsonPath('data.library.status', 'listed');

        // Five a day.
        foreach (range(1, 4) as $i) {
            $this->publish($this->saved())->assertOk();
        }
        $this->assertMatchesContract($this->publish($this->saved()), 'templates.publish', 429);
    }

    public function test_public_templates_are_free_and_a_new_version_is_declared_again(): void
    {
        $this->open();
        $this->creator('ana');
        $public = $this->listed();
        // The free account's one private or unlisted template is still free to make; a second isn't.
        $this->saved();
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $second = (string) Str::uuid();
        $this->putJson('/api/templates/'.$second, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $second, 'version' => 1, 'title' => 'Two', 'reviewed' => true]])->assertStatus(403)->assertJsonPath('code', 'template_limit');

        $body = ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $public, 'version' => 2, 'title' => 'Shall We Gather', 'reviewed' => true]];
        $this->putJson('/api/templates/'.$public, $body)->assertStatus(422)->assertJsonValidationErrors('declaration');
        $this->putJson('/api/templates/'.$public, [...$body, 'declaration' => self::DECLARED])->assertOk()->assertJsonPath('data.versions', [1, 2])->assertJsonPath('data.readOnly', false);
        $this->assertSame('Traditional', Template::query()->with('currentVersion')->where('slug', $public)->sole()->currentVersion?->declaration['source'] ?? null);

        // Unpublished: unlisted, its link still works.
        $out = $this->deleteJson('/api/templates/'.$public.'/publication');
        $this->assertMatchesContract($out, 'templates.unpublish', 200);
        $out->assertJsonPath('data.visibility', 'unlisted')->assertJsonPath('data.library', null);
        $this->assertMatchesContract($this->deleteJson('/api/templates/'.Str::uuid().'/publication'), 'templates.unpublish', 404);
    }

    public function test_the_library_searches_filters_and_sorts_with_everything_a_card_shows(): void
    {
        $this->open();
        $this->creator('ana');
        $grace = $this->listed(['title' => 'Grace Again', 'genre' => ['hymn'], 'tempo' => 70]);
        $quick = $this->listed(['title' => 'Quick Step', 'genre' => ['folk'], 'tempo' => 140, 'difficulty' => 'intermediate']);
        $this->creator('sam');
        $low = $this->listed(['title' => 'Low Down', 'genre' => ['folk'], 'tempo' => 100]);
        // A lower bass part, and versions for 2 or 3 singers only.
        $lowId = (int) Template::query()->where('slug', $low)->value('id');
        TemplatePartRange::query()->where('template_id', $lowId)->where('part_key', 'bass')->update(['low' => 36, 'high' => 47]);
        Template::query()->whereKey($lowId)->update(['voicings' => json_encode([2, 3])]);
        Template::query()->where('slug', $quick)->update(['uses_count' => 5]);
        Template::query()->where('slug', $low)->update(['uses_count' => 2]);

        $list = $this->getJson('/api/library');
        $this->assertMatchesContract($list, 'library.list', 200);
        $this->assertSame([$quick, $low, $grace], array_column((array) $list->json('data'), 'id'));
        $list->assertJsonPath('meta.genres', ['folk', 'hymn'])
            ->assertJsonPath('data.0.library.creator.handle', 'ana')
            ->assertJsonPath('data.0.voicings', [2, 3, 4])
            ->assertJsonPath('data.0.parts.0.range', ['low' => 62, 'high' => 74]);
        $this->assertGreaterThan(0, $list->json('data.0.durationSeconds'));

        $ids = fn (string $query): array => array_column((array) $this->getJson('/api/library?'.$query)->assertOk()->json('data'), 'id');
        $this->assertSame([$grace], $ids('q=grace'));
        $this->assertSame([$quick], $ids('difficulty=intermediate'));
        $this->assertSame([$quick, $low], $ids('genre=folk'));
        $this->assertSame([$grace], $ids('tempo=slow'));
        $this->assertSame([$low], $ids('tempo=medium'));
        $this->assertSame([$quick], $ids('tempo=fast'));
        $this->assertSame([$quick, $grace], $ids('parts=4'));
        // Fits a voice from 35 to 48: only the low song has a part inside it.
        $this->assertSame([$low], $ids('low=35&high=48'));
        $this->assertSame([$low], $ids('creator=sam'));
        $this->assertSame([$low, $quick, $grace], $ids('sort=newest'));
        $this->assertMatchesContract($this->getJson('/api/library?sort=loudest'), 'library.list', 422);

        // The Featured shelf, Hillel's to fill (in sudo).
        $admin = $this->actingAsAdmin();
        $featured = $this->putJson('/api/admin/library/'.$grace.'/feature', ['featured' => true]);
        $this->assertMatchesContract($featured, 'admin.library.feature', 200);
        $featured->assertJsonPath('data.library.featured', true);
        $this->assertMatchesContract($this->putJson('/api/admin/library/'.Str::uuid().'/feature', ['featured' => true]), 'admin.library.feature', 404);
        $this->assertSame([$grace], $ids('tab=featured'));
        $this->assertNotNull($admin);

        // A creator whose profile goes private: their templates leave the library.
        User::query()->where('handle', 'sam')->update(['public_profile_at' => null]);
        $this->assertSame([$quick, $grace], $ids(''));
    }

    public function test_only_someone_who_sang_it_rates_it_and_uses_are_counted_nightly(): void
    {
        $this->open();
        $ana = $this->creator('ana');
        $id = $this->listed();

        $own = $this->putJson('/api/templates/'.$id.'/rating', ['stars' => 5]);
        $this->assertMatchesContract($own, 'templates.rate', 403);
        $own->assertJsonPath('code', 'rating_not_allowed');

        $singers = User::factory()->count(3)->create()->map(fn (User $u): User => $u->fresh() ?? $u);
        $this->actingAs($singers[0]);
        $this->assertMatchesContract($this->putJson('/api/templates/'.$id.'/rating', ['stars' => 4]), 'templates.rate', 403);

        foreach ($singers as $i => $singer) {
            Project::factory()->create(['user_id' => $singer->id, 'template_slug' => $id, 'template_version' => 1]);
            $this->actingAs($singer);
            $rated = $this->putJson('/api/templates/'.$id.'/rating', ['stars' => [5, 4, 3][$i]]);
            $this->assertMatchesContract($rated, 'templates.rate', 200);
            $rated->assertJsonPath('data.mine', [5, 4, 3][$i])->assertJsonPath('data.average', $i < 2 ? null : 4);
        }

        $this->assertMatchesContract($this->putJson('/api/templates/'.$id.'/rating', ['stars' => 9]), 'templates.rate', 422);
        $this->assertMatchesContract($this->putJson('/api/templates/'.Str::uuid().'/rating', ['stars' => 3]), 'templates.rate', 404);
        $back = $this->deleteJson('/api/templates/'.$id.'/rating');
        $this->assertMatchesContract($back, 'templates.unrate', 200);
        $back->assertJsonPath('data', ['average' => null, 'count' => 2, 'mine' => null]);
        $this->assertMatchesContract($this->deleteJson('/api/templates/'.Str::uuid().'/rating'), 'templates.unrate', 404);

        // The creator's own projects never count as uses.
        Project::factory()->create(['user_id' => $ana->id, 'template_slug' => $id, 'template_version' => 1]);
        $this->artisan('app:stats:nightly')->assertSuccessful();
        $this->getJson('/api/library')->assertJsonPath('data.0.library.uses', 3)->assertJsonPath('data.0.library.rating.count', 2);

        // A rater's account goes: their rating with it.
        app(DeleteAccount::class)->handle($singers[0], null);
        $this->assertSame(1, Template::query()->where('slug', $id)->value('ratings_count'));
        $this->assertSame(1, TemplateRating::query()->count());
    }

    public function test_anyone_opens_a_listed_template_credited_and_can_remix_it(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->open();
        $this->creator('ana');
        $id = $this->listed();

        $this->app['auth']->forgetGuards();
        $shown = $this->getJson('/api/templates/'.$id);
        $this->assertMatchesContract($shown, 'templates.show', 200);
        $shown->assertJsonPath('data.grant', 'public')->assertJsonPath('data.library.creator.name', 'Ana');

        // Remixed: a private copy of one's own that links back.
        $this->creator('sam');
        $remix = (string) Str::uuid();
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $made = $this->putJson('/api/templates/'.$remix, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $remix, 'version' => 1, 'title' => 'Shall We Gather (Sam\'s)', 'reviewed' => true], 'parentId' => $id]);
        $this->assertMatchesContract($made, 'templates.save', 200);
        $made->assertJsonPath('data.parent.id', $id)->assertJsonPath('data.parent.creator.handle', 'ana')->assertJsonPath('data.visibility', 'private');
        // From one of our songs too; never from someone's private template.
        $this->creator('max');
        $fromOurs = (string) Str::uuid();
        $this->putJson('/api/templates/'.$fromOurs, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $fromOurs, 'version' => 1, 'title' => 'Grace, my way', 'reviewed' => true], 'parentId' => 'amazing-grace'])->assertOk()->assertJsonPath('data.parent.creator', null);
        $this->creator('kim');
        $private = $this->saved();
        $this->creator('lee', 3);
        $other = (string) Str::uuid();
        $this->putJson('/api/templates/'.$other, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $other, 'version' => 1, 'title' => 'Nope', 'reviewed' => true], 'parentId' => $private])->assertNotFound();

        // Held: as if unlisted. Hidden: its owner alone.
        Template::query()->where('slug', $id)->update(['held_at' => now()]);
        $this->getJson('/api/templates/'.$id)->assertOk()->assertJsonPath('data.grant', 'link');
        Template::query()->where('slug', $id)->update(['held_at' => null, 'hidden_at' => now()]);
        $this->getJson('/api/templates/'.$id)->assertNotFound();
        $this->actingAs(User::query()->where('handle', 'ana')->sole());
        $this->getJson('/api/templates/'.$id)->assertOk()->assertJsonPath('data.grant', 'own')->assertJsonPath('data.library.status', 'hidden');
        // Made unlisted by its owner: still closed to everyone else.
        $this->patchJson('/api/templates/'.$id, ['visibility' => 'unlisted'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/templates/'.$id)->assertNotFound();
    }

    public function test_reports_hold_a_template_and_hillel_approves_hides_and_shows_it_again(): void
    {
        $this->open();
        $ana = $this->creator('ana');
        $id = $this->listed();
        $other = $this->saved();
        $this->publish($other)->assertOk();

        // Its queue: a new creator's first, waiting.
        $admin = $this->actingAsAdmin();
        $held = $this->getJson('/api/admin/library/held');
        $this->assertMatchesContract($held, 'admin.library.held', 200);
        $held->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other)->assertJsonPath('data.0.declaration.song', 'public_domain');
        $approved = $this->postJson('/api/admin/library/'.$other, ['decision' => 'approve']);
        $this->assertMatchesContract($approved, 'admin.library.decide', 200);
        $approved->assertJsonCount(0, 'data');
        $this->assertMatchesContract($this->postJson('/api/admin/library/'.$other, ['decision' => 'approve']), 'admin.library.decide', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/library/'.$other, ['decision' => 'hide']), 'admin.library.decide', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/library/'.Str::uuid(), ['decision' => 'approve']), 'admin.library.decide', 404);

        // Reports from three people (networks), one signed in and verified: held out of the library.
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->postJson('/api/reports', ['templateId' => $id, 'reason' => 'copyright'])->assertNoContent();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->postJson('/api/reports', ['templateId' => $id, 'reason' => 'copyright'])->assertNoContent();
        $this->assertNull(Template::query()->where('slug', $id)->value('held_at'));
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.3'])->postJson('/api/reports', ['templateId' => $id, 'reason' => 'copyright'])->assertNoContent();
        $this->assertNotNull(Template::query()->where('slug', $id)->value('held_at'));
        $this->postJson('/api/reports', ['templateId' => (string) Str::uuid(), 'reason' => 'copyright'])->assertNotFound();

        // Hidden: its owner is told why, and can appeal.
        $this->actingAsAdmin($admin);
        $this->getJson('/api/admin/library/held')->assertJsonPath('data.0.reports', ['count' => 3, 'reasons' => ['copyright']]);
        $this->postJson('/api/admin/library/'.$id, ['decision' => 'hide', 'note' => 'The arrangement is a published one.'])->assertOk();
        $notice = Notice::query()->where('user_id', $ana->id)->sole();
        $this->assertSame([Notice::TEMPLATE_HIDDEN, 'copyright'], [$notice->kind, $notice->rule]);
        $this->assertSame(3, Report::query()->where('status', Report::UPHELD)->count());
        $this->assertMatchesContract($this->putJson('/api/admin/library/'.$id.'/feature', ['featured' => true]), 'admin.library.feature', 409);

        // Its owner unpublishes it meanwhile: Hillel can still show it again (an appeal won).
        $this->actingAs($ana);
        $this->deleteJson('/api/templates/'.$id.'/publication')->assertOk();
        $this->actingAsAdmin($admin);
        $this->postJson('/api/admin/library/'.$id, ['decision' => 'show', 'note' => 'The arranger confirmed it\'s theirs.'])->assertOk();
        $this->actingAs($ana);
        // Published again by a creator still in their first two: it waits for review again.
        $this->publish($id)->assertOk()->assertJsonPath('data.library.status', 'held');
        $this->assertNull(Template::query()->where('slug', $id)->value('hidden_at'));
        $this->getJson('/api/library')->assertJsonCount(1, 'data');
    }
}
