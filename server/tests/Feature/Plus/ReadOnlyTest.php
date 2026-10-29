<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\Support\Plus;
use Tests\TestCase;

/**
 * After a downgrade nothing is deleted: projects beyond the limit, and those made from a Plus song,
 * are read-only (docs/pricing-plan.md, "Downgrades").
 */
final class ReadOnlyTest extends TestCase
{
    use CloudSave;
    use Plus;
    use RefreshDatabase;

    public function test_a_downgrade_leaves_the_most_recently_edited_editable_and_the_person_may_choose(): void
    {
        $this->fakeStripe();
        $user = $this->member();
        $this->subscribe($user);
        $this->actingAs($this->fresh($user));

        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $id = (string) Str::uuid();
            $this->saveProject($id)->assertOk();
            $this->travel(1)->minutes();
        }

        $this->stripeEvent('customer.subscription.deleted', $this->subscription($this->fresh($user), 'canceled'))->assertOk();
        $this->actingAs($this->fresh($user));

        $readOnly = collect((array) $this->pull()->json('data.projects'))->pluck('readOnly', 'id')->all();
        $this->assertSame([$ids[0] => 'limit', $ids[1] => 'limit', $ids[2] => null, $ids[3] => null, $ids[4] => null], $readOnly);

        $refused = $this->saveProject($ids[0], 1, ['title' => 'Changed']);
        $this->assertMatchesContract($refused, 'projects.save', 403);
        $refused->assertJsonPath('code', 'project_read_only')->assertJsonPath('projectId', $ids[0])->assertJsonPath('reason', 'limit');
        $this->saveProject($ids[0], 1, ['settings' => ['practice' => ['focus' => 'x']]])->assertOk();

        $chosen = $this->putJson('/api/projects/editable', ['ids' => [$ids[0], $ids[1], $ids[4]]]);
        $this->assertMatchesContract($chosen, 'projects.editable.update', 200);
        $chosen->assertJsonPath('data.usage.plan', 'free');
        $this->assertEqualsCanonicalizing([$ids[2] => 'limit', $ids[3] => 'limit'], array_filter(collect((array) $chosen->json('data.projects'))->pluck('readOnly', 'id')->all()));

        $this->assertMatchesContract($this->putJson('/api/projects/editable', ['ids' => [...$ids]]), 'projects.editable.update', 422);
    }

    public function test_a_project_from_a_plus_song_waits_for_plus(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->owner();
        $id = (string) Str::uuid();
        $this->saveProject($id, 0, ['template' => ['id' => 'silent-night', 'version' => 1]])->assertOk();

        $this->assertSame('plus_song', collect((array) $this->pull()->json('data.projects'))->firstWhere('id', $id)['readOnly'] ?? null);
        $this->putJson('/api/projects/editable', ['ids' => [$id]])->assertStatus(422)->assertJsonValidationErrors('ids.0');
        $this->assertSame(1, Project::query()->count());
    }
}
