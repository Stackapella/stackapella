<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupSong;
use App\Models\Part;
use App\Models\Project;
use App\Models\Score;
use App\Models\Take;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * admin.groups.list: each choir group's practice of its own songs, week by week, as totals only
 * (docs/choir-tracks-plan.md, "How we'll know").
 */
final class GroupPracticeTest extends TestCase
{
    use RefreshDatabase;

    private function take(User $singer, string $slug, string $at): void
    {
        $project = Project::factory()->create(['user_id' => $singer->id, 'template_slug' => $slug]);
        Take::factory()->create(['part_id' => Part::factory()->create(['project_id' => $project->id])->id, 'created_at' => $at]);
    }

    public function test_each_groups_weekly_practice_of_its_own_songs_as_totals_only(): void
    {
        // Wednesday 11 November 2026: this week began Monday the 9th, last week on the 2nd.
        $this->travelTo('2026-11-11T12:00:00Z');
        $director = User::factory()->create(['name' => 'Ana Ruiz']);
        $group = Group::factory()->create(['director_id' => $director->id, 'name' => 'Thursday Singers', 'created_at' => '2026-10-27 12:00:00']);
        GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $director->id, 'role' => GroupMember::DIRECTOR]);
        [$sam, $kim, $lee] = array_map(fn (string $name): User => User::factory()->create(['name' => $name]), ['Zebulon Quiverfeather', 'Kim Sentinelle', 'Lee Frobozz']);
        foreach ([$sam, $kim, $lee] as $member) {
            GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $member->id]);
        }
        GroupSong::factory()->create(['group_id' => $group->id, 'template_id' => Template::factory()->create(['slug' => 'our-song'])->id]);
        $quiet = Group::factory()->create(['name' => 'Riverside Chorale', 'created_at' => '2026-11-03 09:00:00']);

        // This week: Sam twice and a scored run, Kim once. Last week: Lee's run.
        $this->take($sam, 'our-song', '2026-11-09 08:00:00');
        $this->take($sam, 'our-song', '2026-11-10 19:00:00');
        Score::factory()->create(['user_id' => $sam->id, 'template_slug' => 'our-song', 'created_at' => '2026-11-10 19:05:00']);
        $this->take($kim, 'our-song', '2026-11-11 07:00:00');
        Score::factory()->create(['user_id' => $lee->id, 'template_slug' => 'our-song', 'created_at' => '2026-11-04 18:00:00']);
        // Not counted: another song, someone outside the group, the director, and a binned or unfinished take.
        $this->take($sam, 'amazing-grace', '2026-11-10 20:00:00');
        $this->take(User::factory()->create(), 'our-song', '2026-11-10 20:00:00');
        $this->take($director, 'our-song', '2026-11-10 20:00:00');
        $project = Project::factory()->create(['user_id' => $kim->id, 'template_slug' => 'our-song']);
        Take::factory()->create(['part_id' => Part::factory()->create(['project_id' => $project->id])->id, 'created_at' => '2026-11-10 21:00:00', 'binned_at' => now()]);
        Take::factory()->uploading()->create(['part_id' => Part::factory()->create(['project_id' => $project->id])->id, 'created_at' => '2026-11-10 21:00:00']);
        // Before the 8 weeks shown.
        $this->take($kim, 'our-song', '2026-09-01 10:00:00');

        $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/groups');
        $this->assertMatchesContract($list, 'admin.groups.list', 200);
        $list->assertJsonPath('data.total', 2)->assertJsonPath('data.timezone', 'UTC');
        $this->assertSame(['Thursday Singers', 'Riverside Chorale'], array_column((array) $list->json('data.groups'), 'name'));

        $shown = (array) $list->json('data.groups.0');
        $this->assertSame([$group->sqid(), ['id' => $director->sqid(), 'name' => 'Ana Ruiz'], 3, 1], [$shown['id'], $shown['director'], $shown['members'], $shown['songs']]);
        $this->assertCount(8, $shown['weeks']);
        $this->assertSame(['week' => '2026-11-09', 'singers' => 2, 'takes' => 3, 'scores' => 1], $shown['weeks'][7]);
        $this->assertSame(['week' => '2026-11-02', 'singers' => 1, 'takes' => 0, 'scores' => 1], $shown['weeks'][6]);
        $this->assertSame('2026-09-21', $shown['weeks'][0]['week']);
        $this->assertSame(0, array_sum(array_column(array_slice($shown['weeks'], 0, 6), 'takes')));
        // Totals only: nothing in the answer names a member.
        foreach ([$sam, $kim, $lee] as $member) {
            $this->assertStringNotContainsString((string) $member->name, (string) $list->getContent());
        }
        $this->assertSame(0, array_sum(array_column((array) $list->json('data.groups.1.weeks'), 'singers')));
        $this->assertSame($quiet->sqid(), $list->json('data.groups.1.id'));
    }

    public function test_only_an_admin_sees_it(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/groups')->assertForbidden();
    }
}
