<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Actions\Users\DeleteAccount;
use App\Models\BellItem;
use App\Models\SongRequest;
use App\Models\User;
use App\Support\Flags;
use App\Support\Ids;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Song requests (N7; contract/api.md, "Song requests (N7)"): asked for and voted on, merged and
 * released by Hillel, and everyone who wanted one told when it's ready.
 */
final class SongRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function open(): void
    {
        app(Flags::class)->set('song_requests', 'on', null, 'Opening song requests');
    }

    private function person(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'trust_level_override' => 2])->fresh() ?? throw new \LogicException;
        $this->actingAs($user);

        return $user;
    }

    public function test_requests_are_closed_until_their_flag_opens(): void
    {
        $this->person('Ana');

        $this->assertMatchesContract($this->getJson('/api/requests'), 'requests.list', 404);
        $closed = $this->postJson('/api/requests', ['title' => 'Danny Boy']);
        $this->assertMatchesContract($closed, 'requests.create', 409);
        $closed->assertJsonPath('code', 'requests_closed');
    }

    public function test_people_ask_vote_and_the_same_title_is_a_vote(): void
    {
        $this->open();
        $ana = $this->person('Ana');

        $asked = $this->postJson('/api/requests', ['title' => 'Danny Boy', 'writer' => 'Traditional', 'why' => 'Our choir sings it at Christmas']);
        $this->assertMatchesContract($asked, 'requests.create', 201);
        $asked->assertJsonPath('data.votes', 1)->assertJsonPath('data.voted', true)->assertJsonPath('data.mine', true);
        $id = (string) $asked->json('data.id');
        $this->assertMatchesContract($this->postJson('/api/requests', ['title' => 'See https://spam.example']), 'requests.create', 422);
        $this->postJson('/api/requests', ['title' => '!!!'])->assertStatus(422)->assertJsonValidationErrors('title');

        // Sam asks for the same song: a vote for Ana's.
        $this->person('Sam');
        $same = $this->postJson('/api/requests', ['title' => 'danny boy!']);
        $this->assertMatchesContract($same, 'requests.create', 200);
        $same->assertJsonPath('data.id', $id)->assertJsonPath('data.votes', 2)->assertJsonPath('data.mine', false);
        $this->assertSame(1, SongRequest::query()->count());

        // Kim votes, takes it back.
        $this->person('Kim');
        $voted = $this->putJson('/api/requests/'.$id.'/vote');
        $this->assertMatchesContract($voted, 'requests.vote', 200);
        $voted->assertJsonPath('data.votes', 3);
        $this->putJson('/api/requests/'.$id.'/vote')->assertJsonPath('data.votes', 3);
        $this->assertMatchesContract($this->deleteJson('/api/requests/'.$id.'/vote'), 'requests.unvote', 200);
        $this->assertMatchesContract($this->putJson('/api/requests/nope/vote'), 'requests.vote', 404);
        $this->assertMatchesContract($this->deleteJson('/api/requests/nope/vote'), 'requests.unvote', 404);
        $this->actingAs(User::factory()->create(['trust_level_override' => 0])->fresh() ?? throw new \LogicException);
        $this->assertMatchesContract($this->putJson('/api/requests/'.$id.'/vote'), 'requests.vote', 403);

        // The list: by votes, searchable.
        $this->actingAs($ana);
        $this->postJson('/api/requests', ['title' => 'Shenandoah'])->assertCreated();
        $list = $this->getJson('/api/requests');
        $this->assertMatchesContract($list, 'requests.list', 200);
        $this->assertSame(['Danny Boy', 'Shenandoah'], array_column((array) $list->json('data'), 'title'));
        $this->assertSame(['Shenandoah'], array_column((array) $this->getJson('/api/requests?q=shenan')->json('data'), 'title'));
        $this->assertSame(['Shenandoah', 'Danny Boy'], array_column((array) $this->getJson('/api/requests?sort=newest')->json('data'), 'title'));
        $this->assertMatchesContract($this->getJson('/api/requests?sort=loudest'), 'requests.list', 422);
    }

    public function test_hillel_merges_and_releases_and_everyone_who_wanted_it_hears(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->open();
        $ana = $this->person('Ana');
        $grace = (string) $this->postJson('/api/requests', ['title' => 'Amazing Grace'])->json('data.id');
        $sam = $this->person('Sam');
        $copy = (string) $this->postJson('/api/requests', ['title' => 'Amazing Grace (hymn)'])->json('data.id');

        $admin = $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/requests');
        $this->assertMatchesContract($list, 'admin.requests.list', 200);
        $merged = $this->patchJson('/api/admin/requests/'.$copy, ['mergeInto' => $grace]);
        $this->assertMatchesContract($merged, 'admin.requests.update', 200);
        $merged->assertJsonPath('data.hidden', true)->assertJsonPath('data.mergedInto', $grace);
        $this->assertSame(2, SongRequest::query()->whereKey(Ids::decode($grace))->value('votes_count'));

        $this->assertMatchesContract($this->patchJson('/api/admin/requests/'.$grace, ['status' => 'ready', 'templateId' => 'nope']), 'admin.requests.update', 422);
        $this->assertMatchesContract($this->patchJson('/api/admin/requests/nope', ['status' => 'checking']), 'admin.requests.update', 404);
        $ready = $this->patchJson('/api/admin/requests/'.$grace, ['status' => 'ready', 'templateId' => 'amazing-grace']);
        $ready->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.template.id', 'amazing-grace');
        // Told once, both of them; never again.
        $this->patchJson('/api/admin/requests/'.$grace, ['status' => 'ready', 'templateId' => 'amazing-grace'])->assertOk();
        $this->assertSame([$ana->id, $sam->id], BellItem::query()->where('kind', 'request_ready')->orderBy('user_id')->pluck('user_id')->all());
        $this->assertNotNull($admin);

        $this->actingAs($ana);
        $this->getJson('/api/notifications')->assertJsonPath('data.0.request.id', $grace);
        $this->getJson('/api/requests')->assertJsonCount(1, 'data')->assertJsonPath('data.0.template.id', 'amazing-grace');

        // Sam's account goes: his vote with it, the request stays.
        app(DeleteAccount::class)->handle($sam, null);
        $this->assertSame(1, SongRequest::query()->whereKey(Ids::decode($grace))->value('votes_count'));
    }

    public function test_held_words_and_reports_keep_a_request_out_of_sight(): void
    {
        $this->open();
        $this->person('Ana');
        $held = $this->postJson('/api/requests', ['title' => 'Call me on 555 0101 0101'])->assertCreated();
        $this->getJson('/api/requests')->assertJsonCount(0, 'data');
        // Asking for the same title again never shows the held one's words back.
        $this->person('Sam');
        $again = $this->postJson('/api/requests', ['title' => 'Call me on 555 0101 0101'])->assertCreated();
        $this->assertNotSame($held->json('data.id'), $again->json('data.id'));
        $this->person('Ana');

        $id = (string) $this->postJson('/api/requests', ['title' => 'Shenandoah'])->json('data.id');
        $this->app['auth']->forgetGuards();
        foreach (['203.0.113.1', '203.0.113.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/reports', ['requestId' => $id, 'reason' => 'offensive'])->assertNoContent();
        }
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.3'])->postJson('/api/reports', ['requestId' => $id, 'reason' => 'offensive'])->assertNoContent();
        $this->getJson('/api/requests')->assertJsonCount(0, 'data');

        $this->actingAsAdmin();
        $this->patchJson('/api/admin/requests/'.$id, ['approve' => true])->assertOk()->assertJsonPath('data.held', false);
        $this->patchJson('/api/admin/requests/'.$held->json('data.id'), ['hidden' => true])->assertOk();
        $this->getJson('/api/requests')->assertJsonCount(1, 'data');
    }
}
