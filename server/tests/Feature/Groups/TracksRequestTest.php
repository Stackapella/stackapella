<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * groups.tracks.create (docs/choir-tracks-plan.md, stage 1): a director asks for learning tracks of one piece, kept
 * in the support inbox from their account with their word on its rights, never a score and never another address;
 * Admin finds the requests by category, each with what it needs to give the song.
 */
final class TracksRequestTest extends TestCase
{
    use RefreshDatabase;

    private const PIECE = ['piece' => 'God So Loved the World', 'composer' => 'John Stainer', 'voicing' => 'SATB', 'rights' => 'public-domain', 'rightsConfirmed' => true, 'note' => 'We sing it at Easter.'];

    public function test_a_director_asks_for_learning_tracks_and_admin_finds_it_with_the_group_and_the_rights(): void
    {
        $director = User::factory()->create(['name' => 'Ana Ruiz', 'email' => 'ana@example.com'])->fresh() ?? throw new \RuntimeException('no user');
        $this->actingAs($director);
        $group = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');

        // The rights tick is required, and only the two kinds of piece stage 1 takes.
        $this->assertMatchesContract($this->postJson('/api/groups/'.$group.'/tracks', [...self::PIECE, 'rightsConfirmed' => false]), 'groups.tracks.create', 422);
        $this->postJson('/api/groups/'.$group.'/tracks', [...self::PIECE, 'rights' => 'published-arrangement'])->assertJsonValidationErrors('rights');
        $this->postJson('/api/groups/'.$group.'/tracks', [...self::PIECE, 'piece' => ''])->assertJsonValidationErrors('piece');

        $this->travelTo(now()->setTime(10, 0));
        $sent = $this->postJson('/api/groups/'.$group.'/tracks', [...self::PIECE, 'email' => 'someone-else@example.com', 'score' => 'data:application/pdf;base64,AAAA']);
        $this->assertMatchesContract($sent, 'groups.tracks.create', 201);

        $message = SupportMessage::query()->sole();
        // From their account's own address; nothing else they might send is kept (no score, no other email).
        $this->assertSame(['tracks', 'ana@example.com', $director->id, 'We sing it at Easter.'], [$message->category, $message->email, $message->user_id, $message->body]);
        $this->assertStringNotContainsString('someone-else', (string) json_encode($message->context));
        $this->assertStringNotContainsString('base64', (string) json_encode($message->context));
        $this->assertEquals(['groupId' => $group, 'group' => 'Thursday Singers', 'piece' => 'God So Loved the World', 'composer' => 'John Stainer', 'voicing' => 'SATB', 'rights' => 'public-domain', 'rightsConfirmedAt' => now()->utc()->toIso8601ZuluString()], $message->context['tracks'] ?? null);

        // No note: the body says what it is.
        $this->postJson('/api/groups/'.$group.'/tracks', ['piece' => 'Our own Shenandoah', 'rights' => 'own-arrangement', 'rightsConfirmed' => true])->assertCreated();
        $this->assertSame('Learning tracks, please: Our own Shenandoah.', SupportMessage::query()->latest('id')->firstOrFail()->body);

        // Admin: the requests by category, each with the group, the piece and the rights, and who asked.
        $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/support?category=tracks');
        $this->assertMatchesContract($list, 'admin.support.list', 200);
        $this->assertSame(['Our own Shenandoah', 'God So Loved the World'], array_column(array_column((array) $list->json('data'), 'tracks'), 'piece'));
        $list->assertJsonPath('data.1.from.account.id', $director->sqid())->assertJsonPath('data.1.tracks.groupId', $group)->assertJsonPath('data.0.tracks.composer', null);
        $this->assertSame([], $this->getJson('/api/admin/support?category=payments')->json('data'));
        $this->getJson('/api/admin/support?category=nonsense')->assertJsonValidationErrors('category');

        $shown = $this->getJson('/api/admin/support/'.$message->sqid());
        $this->assertMatchesContract($shown, 'admin.support.show', 200);
        $shown->assertJsonPath('data.tracks.rights', 'public-domain');
        $this->assertContains('tracks_score', array_column((array) $shown->json('data.savedReplies'), 'key'));

        // An ordinary message has no tracks field.
        SupportMessage::factory()->create(['category' => 'recording']);
        $this->assertArrayNotHasKey('tracks', (array) $this->getJson('/api/admin/support')->json('data.0'));
    }

    public function test_only_the_groups_director_can_ask_and_the_contact_form_cant_pose_as_one(): void
    {
        $director = User::factory()->create()->fresh() ?? throw new \RuntimeException('no user');
        $this->actingAs($director);
        $group = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$group.'/link')->assertCreated()->json('data.url'), '/choir/');

        // A member is told it's the director's; someone outside finds no group; a guest must sign in.
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \RuntimeException('no user'));
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertSuccessful();
        $this->assertMatchesContract($this->postJson('/api/groups/'.$group.'/tracks', self::PIECE), 'groups.tracks.create', 403);
        $this->actingAs(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/groups/'.$group.'/tracks', self::PIECE), 'groups.tracks.create', 404);
        $this->assertSame(0, SupportMessage::query()->count());

        // Contact us can't send a tracks message, which only comes from a director.
        $this->postJson('/api/support', ['category' => 'tracks', 'message' => 'Learning tracks, please.', 'email' => 'ana@example.com'])->assertJsonValidationErrors('category');

        // Limited as support is: five an hour.
        $this->actingAs($director);
        foreach (range(1, 5) as $n) {
            $this->postJson('/api/groups/'.$group.'/tracks', [...self::PIECE, 'piece' => 'Piece '.$n])->assertCreated();
        }
        $this->assertMatchesContract($this->postJson('/api/groups/'.$group.'/tracks', self::PIECE), 'groups.tracks.create', 429);
    }

    public function test_a_guest_is_asked_to_sign_in(): void
    {
        $this->postJson('/api/groups/AbC123/tracks', self::PIECE)->assertUnauthorized();
    }
}
