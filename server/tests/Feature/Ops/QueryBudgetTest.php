<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Harmony;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Part;
use App\Models\Project;
use App\Models\StoreSubscription;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The heavy reads (docs/performance.md): each one's queries don't grow with what it lists (no
 * N+1), and stay under a ceiling. Each is measured with a few items, then with many more. About
 * 6 of each request's queries are the rate limiters' bookkeeping in the database cache.
 * QUERY_BUDGET_REPORT=1 prints the counts.
 */
final class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * How many queries a request makes, and its answer.
     *
     * @return array{0: int, 1: TestResponse}
     */
    private function measure(callable $request): array
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        DB::flushQueryLog();
        $this->app['auth']->forgetGuards();
        $response = $request();
        $counted = $count;
        // A listener can't be removed: later queries count into a variable nothing reads.
        $count = PHP_INT_MIN;

        return [$counted, $response];
    }

    /**
     * The request's queries with `$few` items and with `$many`, which must be the same, and at
     * most `$ceiling`.
     */
    private function assertFlat(string $what, callable $seed, callable $request, int $ceiling, int $few = 2, int $many = 12): void
    {
        $seed($few);
        // Once to warm what's loaded once per process (flags, settings, the contract's caches).
        $this->measure($request);
        [$small, $first] = $this->measure($request);
        $first->assertOk();
        $seed($many - $few);
        [$large, $second] = $this->measure($request);
        $second->assertOk();

        // The seeding really is listed: a list answers more items the second time.
        $listed = static fn (TestResponse $r): ?int => is_array($r->json('data')) && array_is_list($r->json('data')) ? count($r->json('data')) : null;
        if ($listed($first) !== null) {
            $this->assertGreaterThan($listed($first), (int) $listed($second), "{$what}: the seeded items aren't listed.");
        }

        if (getenv('QUERY_BUDGET_REPORT') !== false) {
            fwrite(STDERR, sprintf("%-32s %3d queries (budget %d)\n", $what, $large, $ceiling));
        }

        $this->assertSame($small, $large, "{$what}: {$small} queries with {$few}, {$large} with {$many} (one per item: an N+1).");
        $this->assertLessThanOrEqual($ceiling, $large, "{$what}: {$large} queries, over its budget of {$ceiling}.");
    }

    private function adult(): User
    {
        return User::factory()->create(['handle' => 'singer_'.(++$this->singers), 'adult_confirmed_at' => now(), 'public_profile_at' => now(), 'trust_level_override' => 2])->fresh() ?? throw new \LogicException;
    }

    private function tune(User $owner): Template
    {
        $template = Template::factory()->create(['owner_id' => $owner->id]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => true]);
        $template->forceFill(['current_version_id' => $version->id])->save();

        return $template;
    }

    public function test_the_catalogue(): void
    {
        $seed = function (int $n): void {
            foreach (range(1, $n) as $i) {
                $template = Template::factory()->create();
                $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => true]);
                $template->forceFill(['current_version_id' => $version->id])->save();
            }
        };

        $this->assertFlat('templates.catalogue', $seed, fn () => $this->getJson('/api/templates'), 20);
        $this->assertFlat('templates.catalogue, signed in', fn (int $n) => $seed($n), fn () => $this->actingAs($this->viewer ??= $this->adult())->getJson('/api/templates'), 25, 1, 6);
    }

    private ?User $viewer = null;

    private int $singers = 0;

    public function test_a_sync_pull_of_many_projects(): void
    {
        $owner = $this->adult();
        $seed = function (int $n) use ($owner): void {
            foreach (range(1, $n) as $i) {
                $project = Project::factory()->create(['user_id' => $owner->id]);
                $part = Part::factory()->create(['project_id' => $project->id]);
                Take::factory()->create(['part_id' => $part->id, 'project_id' => $project->id, 'user_id' => $owner->id]);
            }
        };

        $this->assertFlat('sync.pull', $seed, fn () => $this->actingAs($owner)->getJson('/api/sync?since=0'), 30);
    }

    public function test_the_gallery(): void
    {
        app(Flags::class)->set('gallery', 'on', null, 'Measuring');
        $seed = function (int $n): void {
            foreach (range(1, $n) as $i) {
                $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $this->adult()->id, 'gallery_opt_in_at' => now(), 'rights_confirmed_at' => now()]);
                Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
                Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
                app(HarmonyFiles::class)->sync($harmony);
            }
        };

        $this->assertFlat('explore.list', $seed, fn () => $this->getJson('/api/explore'), 25);
        $this->assertFlat('explore.list, signed in', fn (int $n) => $seed($n), fn () => $this->actingAs($this->viewer ??= $this->adult())->getJson('/api/explore'), 30, 1, 4);
    }

    public function test_the_ideas_shelf_and_an_idea(): void
    {
        app(Flags::class)->set('ideas', 'on', null, 'Measuring');
        $idea = null;
        $seed = function (int $n) use (&$idea): void {
            foreach (range(1, $n) as $i) {
                $writer = $this->adult();
                $idea = Idea::factory()->create(['user_id' => $writer->id, 'template_id' => $this->tune($writer)->id]);
                IdeaVoice::factory()->create(['idea_id' => $idea->id, 'user_id' => $this->adult()->id, 'part_key' => 'alto']);
            }
        };

        $this->assertFlat('ideas.list', $seed, fn () => $this->getJson('/api/ideas'), 25);

        $one = Idea::query()->latest('id')->firstOrFail();
        $voices = function (int $n) use ($one): void {
            foreach (range(1, $n) as $i) {
                IdeaVoice::factory()->create(['idea_id' => $one->id, 'user_id' => $this->adult()->id, 'part_key' => ['soprano', 'alto', 'tenor', 'bass'][$i % 4]]);
            }
        };
        $this->assertFlat('ideas.show', $voices, fn () => $this->getJson('/api/ideas/'.$one->public_id), 28);
    }

    public function test_a_choir_and_its_voices(): void
    {
        app(Flags::class)->set('virtual_choir', 'on', null, 'Measuring');
        $template = Template::factory()->create(['slug' => 'amazing-grace']);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => true]);
        $template->forceFill(['current_version_id' => $version->id])->save();
        $choir = Choir::factory()->create(['template_id' => $template->id]);
        $seed = function (int $n) use ($choir): void {
            foreach (range(1, $n) as $i) {
                ChoirVoice::factory()->create(['choir_id' => $choir->id, 'user_id' => $this->adult()->id, 'in_mix_at' => now()]);
            }
        };

        $this->assertFlat('choirs.show', $seed, fn () => $this->getJson('/api/choirs/amazing-grace'), 24);
        $this->assertFlat('choirs.voices.list', $seed, fn () => $this->getJson('/api/choirs/amazing-grace/voices'), 24);
    }

    public function test_the_storage_screen_and_groups(): void
    {
        $owner = $this->adult();
        $seed = function (int $n) use ($owner): void {
            foreach (range(1, $n) as $i) {
                $project = Project::factory()->create(['user_id' => $owner->id]);
                $part = Part::factory()->create(['project_id' => $project->id]);
                Take::factory()->create(['part_id' => $part->id, 'project_id' => $project->id, 'user_id' => $owner->id, 'binned_at' => now()->subDay()]);
                $current = Take::factory()->create(['part_id' => $part->id, 'project_id' => $project->id, 'user_id' => $owner->id]);
                $part->forceFill(['current_take_id' => $current->id])->save();
            }
        };
        $this->assertFlat('usage.show', $seed, fn () => $this->actingAs($owner)->getJson('/api/usage'), 17);
        $this->assertFlat('bin.list', fn (int $n) => $seed($n), fn () => $this->actingAs($owner)->getJson('/api/bin'), 15);

        $groups = function (int $n) use ($owner): void {
            foreach (range(1, $n) as $i) {
                $group = Group::factory()->create(['director_id' => $this->adult()->id]);
                GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
                GroupMember::factory()->count(3)->create(['group_id' => $group->id]);
            }
        };
        $this->assertFlat('groups.list', $groups, fn () => $this->actingAs($owner)->getJson('/api/groups'), 14);
    }

    public function test_the_reads_every_visit_makes(): void
    {
        $user = $this->adult();
        $ceilings = ['auth.config' => ['/api/auth/config', null, 12], 'billing.prices' => ['/api/billing/prices', null, 11], 'account.show' => ['/api/account', $user, 14]];

        foreach ($ceilings as $what => [$path, $as, $ceiling]) {
            $request = fn () => $as !== null ? $this->actingAs($as)->getJson($path) : $this->getJson($path);
            $this->measure($request);
            [$count, $response] = $this->measure($request);
            $response->assertOk();
            if (getenv('QUERY_BUDGET_REPORT') !== false) {
                fwrite(STDERR, sprintf("%-32s %3d queries (budget %d)\n", $what, $count, $ceiling));
            }

            $this->assertLessThanOrEqual($ceiling, $count, "{$what}: {$count} queries, over its budget of {$ceiling}.");
        }
    }

    public function test_the_admin_people_list_with_each_persons_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $people = function (int $n): void {
            foreach (range(1, $n) as $i) {
                $person = $this->adult();
                $project = Project::factory()->create(['user_id' => $person->id]);
                $part = Part::factory()->create(['project_id' => $project->id]);
                Take::factory()->create(['part_id' => $part->id, 'project_id' => $project->id, 'user_id' => $person->id]);
                (new UserLearning)->forceFill(['user_id' => $person->id, 'version' => 1, 'progress' => ['lessons' => ['1-a' => ['stars' => 2]], 'xp' => ['total' => 120, 'days' => []], 'weeks' => ['streak' => 1, 'last' => now()->startOfWeek()->toDateString()], 'practice' => []]])->save();
            }
        };
        $this->assertFlat('admin.people.list', $people, function () use ($admin): TestResponse {
            $this->actingAsAdmin($admin);

            return $this->getJson('/api/admin/people');
        }, 12);
    }

    public function test_billing_and_learning(): void
    {
        $user = $this->adult();
        $seed = function (int $n) use ($user): void {
            StoreSubscription::factory()->count($n)->create(['user_id' => $user->id]);
        };
        $this->assertFlat('billing.show', $seed, fn () => $this->actingAs($user)->getJson('/api/billing'), 20);

        UserLearning::factory()->create(['user_id' => $user->id]);
        [$learning] = $this->measure(fn () => $this->actingAs($user)->getJson('/api/account/learning')->assertOk());
        if (getenv('QUERY_BUDGET_REPORT') !== false) {
            fwrite(STDERR, sprintf("%-32s %3d queries (budget %d)\n", 'account.learning.show', $learning, 10));
        }

        $this->assertLessThanOrEqual(10, $learning, "account.learning.show: {$learning} queries.");
    }
}
