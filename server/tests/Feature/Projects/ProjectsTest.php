<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\TestCase;

final class ProjectsTest extends TestCase
{
    use CloudSave;
    use RefreshDatabase;

    public function test_a_project_is_made_changed_and_comes_back_by_its_device_uuid(): void
    {
        $this->owner();
        $id = (string) Str::uuid();

        $made = $this->saveProject($id);
        $this->assertMatchesContract($made, 'projects.save', 200);
        $made->assertJsonPath('data.id', $id)->assertJsonPath('data.version', 1)->assertJsonPath('data.template.id', 'amazing-grace')->assertJsonPath('data.createdAt', '2026-10-20T09:00:00Z');

        $this->saveProject($id, 1, ['title' => 'Grace (choir)'])->assertJsonPath('data.version', 2)->assertJsonPath('data.title', 'Grace (choir)');

        $shown = $this->getJson('/api/projects/'.$id);
        $this->assertMatchesContract($shown, 'projects.show', 200);
        $shown->assertJsonPath('data.project.title', 'Grace (choir)');
    }

    public function test_a_stale_save_is_a_conflict_with_the_servers_copy_and_a_replay_is_not(): void
    {
        $this->owner();
        $id = (string) Str::uuid();
        $this->saveProject($id)->assertOk();
        $this->saveProject($id, 1, ['title' => 'From the phone'])->assertOk();

        $stale = $this->saveProject($id, 1, ['title' => 'From the Mac']);
        $this->assertMatchesContract($stale, 'projects.save', 409);
        $stale->assertJsonPath('code', 'version_conflict')->assertJsonPath('current.title', 'From the phone')->assertJsonPath('current.version', 2);

        // The same change again (its answer was lost): as it is, never a conflict.
        $change = (string) Str::uuid();
        $this->saveProject($id, 2, ['changeId' => $change, 'title' => 'Kept'])->assertJsonPath('data.version', 3);
        $this->saveProject($id, 2, ['changeId' => $change, 'title' => 'Kept'])->assertOk()->assertJsonPath('data.version', 3);
    }

    public function test_the_free_plan_holds_3_projects_and_the_bin_doesnt_count(): void
    {
        $this->owner();
        $ids = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        foreach ($ids as $id) {
            $this->saveProject($id)->assertOk();
        }

        $fourth = (string) Str::uuid();
        $this->assertMatchesContract($this->saveProject($fourth), 'projects.save', 403);

        $this->assertMatchesContract($this->deleteJson('/api/projects/'.$ids[0].'?version=1&changeId='.Str::uuid()), 'projects.delete', 204);
        $this->saveProject($fourth)->assertOk();

        $this->assertMatchesContract($this->postJson('/api/projects/'.$ids[0].'/restore'), 'projects.restore', 403);
        $usage = $this->getJson('/api/usage');
        $this->assertMatchesContract($usage, 'usage.show', 200);
        $usage->assertJsonPath('data.projects', ['used' => 3, 'limit' => 3])->assertJsonPath('data.plan', 'free');
    }

    public function test_a_binned_project_waits_and_comes_back(): void
    {
        $this->owner();
        $id = (string) Str::uuid();
        $this->saveProject($id)->assertOk();
        $this->saveProject($id, 1)->assertOk();

        $this->assertMatchesContract($this->deleteJson('/api/projects/'.$id.'?version=1&changeId='.Str::uuid()), 'projects.delete', 409);
        $this->deleteJson('/api/projects/'.$id.'?version=2&changeId='.Str::uuid())->assertNoContent();
        $this->deleteJson('/api/projects/'.$id.'?version=2&changeId='.Str::uuid())->assertNoContent();

        $this->assertMatchesContract($this->saveProject($id, 2), 'projects.save', 409);
        $bin = $this->getJson('/api/bin');
        $this->assertMatchesContract($bin, 'bin.list', 200);
        $bin->assertJsonPath('data.0.kind', 'project')->assertJsonPath('data.0.id', $id);

        $back = $this->postJson('/api/projects/'.$id.'/restore');
        $this->assertMatchesContract($back, 'projects.restore', 200);
        $back->assertJsonPath('data.binnedAt', null);
        $this->postJson('/api/projects/'.$id.'/restore')->assertOk();
    }

    public function test_someone_elses_project_is_not_found_and_a_purged_one_cant_be_saved(): void
    {
        $this->owner();
        $theirs = Project::factory()->create();

        $this->assertMatchesContract($this->getJson('/api/projects/'.$theirs->uuid), 'projects.show', 404);
        $this->saveProject($theirs->uuid)->assertNotFound();
        $this->deleteJson('/api/projects/'.$theirs->uuid.'?version=1&changeId='.Str::uuid())->assertNotFound();
        $this->saveProject((string) Str::uuid(), 4)->assertNotFound();
        $this->assertSame(1, Project::query()->count());
    }

    public function test_the_fields_are_checked(): void
    {
        $this->owner();

        $bad = $this->saveProject((string) Str::uuid(), 0, ['bpm' => 900, 'timeSignature' => 'waltz', 'settings' => ['x' => str_repeat('a', 300 * 1024)]]);
        $this->assertMatchesContract($bad, 'projects.save', 422);
        $bad->assertJsonValidationErrors(['bpm', 'timeSignature', 'settings']);
    }

    public function test_cloud_save_needs_a_sign_in(): void
    {
        $this->assertMatchesContract($this->pull(), 'sync.pull', 401);
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_new_project_may_have_no_name_yet(): void
    {
        $this->owner();
        $id = (string) Str::uuid();

        $blank = $this->saveProject($id, 0, ['title' => '']);
        $this->assertMatchesContract($blank, 'projects.save', 200);
        $blank->assertJsonPath('data.title', '');
        $this->saveProject($id, 1, ['title' => 'Amazing Grace'])->assertJsonPath('data.title', 'Amazing Grace');
    }
}
