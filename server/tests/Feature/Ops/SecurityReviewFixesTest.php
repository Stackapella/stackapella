<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Choirs\Choirs;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\Harmony;
use App\Models\HarmonyConsent;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\User;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixes from the whole-API privacy and security review (2026-11-01), each shown both ways.
 */
final class SecurityReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_friend_on_a_share_is_named_only_once_an_adult_who_agreed(): void
    {
        $owner = User::factory()->create(['name' => 'Ada']);
        $teen = User::factory()->create(['name' => 'Teen', 'adult_confirmed_at' => null]);
        $friend = User::factory()->create(['name' => 'Bo', 'adult_confirmed_at' => now()]);
        $harmony = Harmony::factory()->create(['project_id' => Project::factory()->create(['user_id' => $owner->id])->id, 'singer_ids' => [$teen->id, $friend->id]]);
        $names = fn (): array => array_column(app(HarmonyFacts::class)->singers($harmony->refresh()), 'name');
        $anonymous = __('sharing.a_singer');

        // Not asked yet: neither friend is named on the page, card, video or embed.
        $this->assertSame(['Ada', $anonymous, $anonymous], $names());

        HarmonyConsent::factory()->create(['harmony_id' => $harmony->id, 'user_id' => $friend->id, 'status' => HarmonyConsent::AGREED]);
        // A teen's yes (made in the database: the app refuses it) still never names them.
        HarmonyConsent::factory()->create(['harmony_id' => $harmony->id, 'user_id' => $teen->id, 'status' => HarmonyConsent::AGREED]);
        $this->assertSame(['Ada', $anonymous, 'Bo'], $names());
        $this->assertNull(app(HarmonyFacts::class)->singers($harmony)[1]['avatarUrl']);
    }

    public function test_a_suspended_singers_voice_isnt_heard_in_a_choir(): void
    {
        $choir = Choir::factory()->create();
        ChoirVoice::factory()->create(['choir_id' => $choir->id]);
        ChoirVoice::factory()->create(['choir_id' => $choir->id, 'user_id' => User::factory()->create(['suspended_at' => now()])->id]);

        $this->assertSame(1, Choirs::heard($choir)->count());
    }

    public function test_someone_who_left_a_project_cant_bin_their_old_takes_in_it(): void
    {
        $owner = User::factory()->create();
        $former = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $part = Part::factory()->create(['project_id' => $project->id]);
        $take = Take::factory()->create(['part_id' => $part->id, 'recorded_by' => $former->id]);

        $this->actingAs($former)->deleteJson('/api/takes/'.$take->uuid)->assertNotFound();
        $this->assertNull($take->refresh()->binned_at);

        // The owner still can.
        $this->actingAs($owner)->deleteJson('/api/takes/'.$take->uuid)->assertSuccessful();
        $this->assertNotNull($take->refresh()->binned_at);
    }
}
