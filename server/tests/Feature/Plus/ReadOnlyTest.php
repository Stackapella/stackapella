<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Actions\Templates\SyncOfficialTemplates;
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
        // No plan limits projects since 2026-11-07; an install may set one, and this is that machinery.
        config(['usage.plans.free.projects' => 3]);
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

    public function test_a_project_from_any_library_song_stays_editable_on_the_free_plan(): void
    {
        // Every library song is free with an account, and no plan limits projects (2026-11-07).
        app(SyncOfficialTemplates::class)->handle();
        $this->owner();
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $id = (string) Str::uuid();
            $this->saveProject($id, 0, ['template' => ['id' => 'silent-night', 'version' => 1]])->assertOk();
        }

        $this->assertSame([null], array_values(array_unique(array_column((array) $this->pull()->json('data.projects'), 'readOnly'))));
        $usage = $this->getJson('/api/usage');
        $this->assertMatchesContract($usage, 'usage.show', 200);
        $usage->assertJsonPath('data.projects.limit', null);
        $this->putJson('/api/projects/editable', ['ids' => $ids])->assertOk();
    }
}
