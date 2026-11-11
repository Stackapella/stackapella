<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Breadth's tried set (docs/retention-plan.md, section 8): the features a signed-in singer has tried follow them to
 * another device, merged so nothing is ever taken away and each keeps the day it was first used; never in Admin.
 */
final class TriedTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_devices_add_up_and_each_feature_keeps_its_first_day(): void
    {
        $ada = User::factory()->create()->fresh() ?? throw new \RuntimeException('no user');
        $this->actingAs($ada);
        $this->getJson('/api/account')->assertJsonPath('data.tried', []);

        $this->patchJson('/api/account', ['tried' => ['lesson' => '2026-11-03', 'record' => '2026-11-05']])->assertOk();
        // A second device: one it found earlier, one new; nothing it lacks is dropped.
        $shown = $this->patchJson('/api/account', ['tried' => ['record' => '2026-11-04', 'stack' => '2026-11-06']]);
        $this->assertMatchesContract($shown, 'account.update', 200);
        // (MySQL's JSON column keeps its own key order.)
        $this->assertEquals(['lesson' => '2026-11-03', 'record' => '2026-11-04', 'stack' => '2026-11-06'], $ada->refresh()->tried);
        $this->assertMatchesContract($this->getJson('/api/account'), 'account.show', 200);

        // Only the features breadth counts, each with a day.
        $this->patchJson('/api/account', ['tried' => ['nope' => '2026-11-03']])->assertJsonValidationErrors('tried');
        $this->patchJson('/api/account', ['tried' => ['lesson' => 'yesterday']])->assertJsonValidationErrors('tried.lesson');

        // Theirs alone: Admin's view of the account never lists it.
        $this->actingAsAdmin();
        $this->assertStringNotContainsString('"tried"', (string) $this->getJson('/api/admin/people/'.$ada->sqid())->getContent());
    }
}
