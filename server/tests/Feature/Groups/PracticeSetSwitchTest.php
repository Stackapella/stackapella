<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Practise the set (build plan Brief H) behind practice_set: off for everyone, on for staff first (Hillel's iPhone
 * pass), then for everyone once the switch is on.
 */
final class PracticeSetSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_see_it_before_everyone(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('data.practiceSet', false)->assertJsonPath('data.songPieces', false);
        app(Flags::class)->set('song_pieces', 'on', null, 'test');
        $this->getJson('/api/auth/config')->assertJsonPath('data.songPieces', true);
        $this->actingAs(User::factory()->create())->getJson('/api/auth/config')->assertJsonPath('data.practiceSet', false);

        $editor = User::factory()->create();
        $editor->forceFill(['is_editor' => true])->save();
        $this->actingAs($editor)->getJson('/api/auth/config')->assertJsonPath('data.practiceSet', true);

        app(Flags::class)->set('practice_set', 'on', null, 'test');
        $this->actingAs(User::factory()->create())->getJson('/api/auth/config')->assertJsonPath('data.practiceSet', true);
    }
}
