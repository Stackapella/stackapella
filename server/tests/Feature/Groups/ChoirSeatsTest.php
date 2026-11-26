<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupSeat;
use App\Models\User;
use App\Support\Flags;
use App\Support\Ids;
use App\Support\OwnChoir\OwnChoir;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CloudSave;
use Tests\Support\Plus;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Choir seats (Hillel, 2026-11-18; docs/choir-seats-plan.md; the choir_seats switch): every member of a choir group
 * needs a seat, the director buys them ($15 a singer a year, from 10), a seat is Plus so a member's own Plus counts,
 * the seats fill in join order and move at the director's word, and learning alone stays free. Off, nothing changes.
 */
final class ChoirSeatsTest extends TestCase
{
    use CloudSave;
    use Plus;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SyncOfficialTemplates::class)->handle();
        $this->fakeStripe();
        config(['billing.choir.price' => 'price_choir']);
    }

    private function as(User $user): User
    {
        $user = $user->fresh() ?? $user;
        $this->actingAs($user);

        return $user;
    }

    private function on(): void
    {
        app(Flags::class)->set('choir_seats', 'on', null, 'test');
    }

    /**
     * The signed-in director's own song (Amazing Grace's notes under a new id), saved and private.
     */
    private function ownSong(): string
    {
        $id = (string) Str::uuid();
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $this->putJson('/api/templates/'.$id, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $id, 'version' => 1, 'title' => 'Our Song for Gran', 'reviewed' => true]])->assertOk();

        return $id;
    }

    /**
     * A director (free unless said), their group with its link and their own song shared, and members joined in order.
     *
     * @param  list<string>  $names
     * @return array{director: User, id: string, group: Group, song: string, token: string, members: array<string, User>}
     */
    private function choir(array $names = ['Sam', 'Kim', 'Lee'], string $plan = 'free'): array
    {
        $director = $this->as(User::factory()->create(['name' => 'Ana Ruiz', 'plan' => $plan]));
        $id = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$id.'/link')->assertCreated()->json('data.url'), '/choir/');
        $song = $this->ownSong();
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => $song])->assertOk();
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'amazing-grace'])->assertOk();
        $members = [];

        foreach ($names as $name) {
            $members[$name] = $this->as(User::factory()->create(['name' => $name]));
            $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
            // Joined one after another, so join order is plain.
            $this->travel(1)->seconds();
        }

        $this->as($director);

        return ['director' => $director, 'id' => $id, 'group' => Group::query()->findOrFail(Ids::decode($id)), 'song' => $song, 'token' => $token, 'members' => $members];
    }

    /**
     * Stripe's word on the group's seats subscription, as its webhook sends it.
     *
     * @param  array<string, mixed>  $with
     */
    private function seatsEvent(string $type, Group $group, int $seats, string $status = 'active', array $with = []): TestResponse
    {
        $director = User::query()->findOrFail($group->director_id);
        $director->forceFill(['stripe_id' => $director->stripe_id ?? 'cus_test_'.$director->id])->save();

        return $this->stripeEvent($type, [
            'id' => 'sub_choir_'.$group->id,
            'customer' => $director->stripe_id,
            'status' => $status,
            'trial_end' => $status === 'trialing' ? now()->addDays(14)->getTimestamp() : null,
            'metadata' => ['type' => 'choir_'.$group->id, 'name' => 'choir_'.$group->id, 'kind' => 'choir_seats'],
            'items' => ['data' => [['id' => 'si_choir_'.$group->id, 'quantity' => $seats, 'price' => ['id' => 'price_choir', 'product' => 'prod_choir']]]],
            ...$with,
        ]);
    }

    /** @return array<string, string> each member's seat as the director sees it, by name */
    private function seats(string $id): array
    {
        return collect((array) $this->getJson('/api/groups/'.$id)->assertOk()->json('data.members'))->mapWithKeys(fn (array $m): array => [$m['name'] => $m['seat']])->all();
    }

    /**
     * A project from the song with one part, saved as the signed-in member: [projectId, partId].
     *
     * @return array{0: TestResponse, 1: string}
     */
    private function projectFrom(string $song): array
    {
        $project = (string) Str::uuid();

        return [$this->saveProject($project, 0, ['title' => 'Our Song for Gran', 'template' => ['id' => $song, 'version' => 1]]), $project];
    }

    private function take(string $part): TestResponse
    {
        return $this->startTake((string) Str::uuid(), $part, Wav::tone(0.5));
    }

    public function test_with_the_switch_off_nothing_changes_and_seats_are_off(): void
    {
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        $this->as(User::factory()->create());
        // Teaming up's rule stands: starting a group needs Plus.
        $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertForbidden()->assertJsonPath('code', 'plus_required');

        $c = $this->choir(['Sam'], 'plus');
        $view = $this->getJson('/api/groups/'.$c['id']);
        $this->assertMatchesContract($view, 'groups.show', 200);
        $view->assertJsonPath('data.seat', 'seated')->assertJsonPath('data.seats', null)->assertJsonPath('data.members.1.seat', 'seated')->assertJsonPath('data.songs.0.seat', 'seated');
        $this->getJson('/api/auth/config')->assertJsonPath('data.choirSeats', false);

        // Buying seats is off while the switch is.
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 10, 'currency' => 'usd']), 'groups.seats.checkout', 423);
        // And before Stripe's price is set up, with it on.
        $this->on();
        config(['billing.choir.price' => null]);
        $this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 10, 'currency' => 'usd'])->assertStatus(423);
        app(Flags::class)->set('choir_seats', 'off', null, 'test');

        // A member sings the group's own song free, as today.
        $this->as($c['members']['Sam']);
        $this->getJson('/api/templates/'.$c['song'])->assertOk()->assertJsonPath('data.grant', 'group');
        [$saved, $project] = $this->projectFrom($c['song']);
        $saved->assertOk();
        $part = (string) Str::uuid();
        $this->savePart($part, $project)->assertOk();
        $this->take($part)->assertCreated();

        // Today's pause: the director's Plus ends, the group is read-only and the member's seat follows it.
        User::query()->whereKey($c['director']->id)->update(['plan' => 'free']);
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.readOnly', true)->assertJsonPath('data.seat', 'needed');
        $this->getJson('/api/groups')->assertJsonPath('data.0.seat', 'needed');
    }

    public function test_with_the_switch_on_an_unseated_member_sees_the_group_but_cant_practise_its_songs(): void
    {
        $this->on();
        // Anyone may start a group: the choir pays by the seat. The leader's Plus covers nobody else.
        $c = $this->choir(['Sam'], 'plus');
        $this->getJson('/api/auth/config')->assertJsonPath('data.choirSeats', true);
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.seat', 'seated')->assertJsonPath('data.seats.status', 'none')->assertJsonPath('data.members.0.seat', 'own')->assertJsonPath('data.members.1.seat', 'needed');

        $this->as($c['members']['Sam']);
        $view = $this->getJson('/api/groups/'.$c['id']);
        $this->assertMatchesContract($view, 'groups.show', 200);
        $view->assertJsonPath('data.seat', 'needed')->assertJsonPath('data.readOnly', false)->assertJsonPath('data.seats', null)->assertJsonPath('data.songs.0.seat', 'needed')->assertJsonPath('data.songs.1.seat', 'seated');
        $list = $this->getJson('/api/groups');
        $this->assertMatchesContract($list, 'groups.list', 200);
        $list->assertJsonPath('data.0.seat', 'needed');

        // The group's own song: the calm refusal, everywhere it would be practised.
        $shown = $this->getJson('/api/templates/'.$c['song']);
        $this->assertMatchesContract($shown, 'templates.show', 403);
        $shown->assertJsonPath('code', 'plus_required')->assertJsonPath('reason', 'seat_needed');
        [$saved] = $this->projectFrom($c['song']);
        $this->assertMatchesContract($saved, 'projects.save', 403);
        $saved->assertJsonPath('reason', 'seat_needed');

        // Our library song stays free to learn alone.
        $this->getJson('/api/templates/amazing-grace')->assertOk();
        [, $part] = $this->projectWithPart();
        $this->take($part)->assertCreated();

        // Their own Plus seats them without using one.
        User::query()->whereKey($c['members']['Sam']->id)->update(['plan' => 'plus']);
        $this->as($c['members']['Sam']);
        $this->getJson('/api/templates/'.$c['song'])->assertOk();
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.seat', 'seated');
        $this->assertSame(0, GroupSeat::query()->count());
    }

    public function test_the_director_buys_seats_and_they_fill_in_join_order_never_for_a_member_with_plus(): void
    {
        $this->on();
        $c = $this->choir(['Sam', 'Kim', 'Lee']);
        User::query()->whereKey($c['members']['Kim']->id)->update(['plan' => 'plus']);

        // Only the director, only a verified account, from 10 seats.
        $this->as($c['members']['Sam']);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 10, 'currency' => 'usd']), 'groups.seats.checkout', 403);
        $this->as($c['director']);
        $this->assertMatchesContract($this->postJson('/api/groups/nope/seats/checkout', ['seats' => 10, 'currency' => 'usd']), 'groups.seats.checkout', 404);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 9, 'currency' => 'usd']), 'groups.seats.checkout', 422);
        User::query()->whereKey($c['director']->id)->update(['email_verified_at' => null, 'google_id' => null, 'password' => bcrypt('a-long-password')]);
        $this->as($c['director']);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 10, 'currency' => 'usd']), 'groups.seats.checkout', 403);
        User::query()->whereKey($c['director']->id)->update(['email_verified_at' => now()]);
        $this->as($c['director']);

        $checkout = $this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 10, 'currency' => 'usd']);
        $this->assertMatchesContract($checkout, 'groups.seats.checkout', 201);
        $made = $this->stripe->seatCheckouts[0];
        $this->assertSame(['choir_'.$c['group']->id, 'price_choir', 10, 'usd'], [$made['type'], $made['price'], $made['seats'], $made['currency']]);
        // No choir trial since 2026-11-21: paid from the start.
        $this->assertNull($made['trialEnd']);
        $this->assertStringContainsString('/groups/'.$c['id'].'?seats=bought', (string) $made['successUrl']);
        $this->assertStringContainsString('10 seats', (string) $made['terms']);

        // Stripe's webhook: a pack of 10, trialing. Sam and Lee take seats; Kim keeps her own Plus.
        $this->seatsEvent('customer.subscription.created', $c['group'], 10, 'trialing')->assertOk();
        $this->assertSame(['Ana Ruiz' => 'seated', 'Sam' => 'seated', 'Kim' => 'own', 'Lee' => 'seated'], $this->seats($c['id']));
        $panel = $this->getJson('/api/groups/'.$c['id'])->json('data.seats');
        $this->assertSame(['trialing', 10, 2, 10], [$panel['status'], $panel['bought'], $panel['used'], $panel['min']]);
        $this->assertNotNull($panel['trialEndsAt']);
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'seats_bought_10')->sum('count'), 'a pack bought, by its size band');
        $this->assertSame(0, (int) DB::table('usage_counts')->where('kind', 'plus_started')->sum('count'), 'seats are not Plus bought');
        // Redelivered: counted once.
        $this->seatsEvent('customer.subscription.created', $c['group'], 10, 'trialing')->assertOk();
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'seats_bought_10')->sum('count'));

        // A seat is Plus: the seated singer has every Plus extra, and billing says where it comes from.
        $this->as($c['members']['Sam']);
        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'plus')->assertJsonPath('data.source', 'choir');
        $this->getJson('/api/templates/'.$c['song'])->assertOk();
        // The director's own account isn't Plus's subscription: their Plus subscription is untouched.
        $this->assertFalse(User::query()->findOrFail($c['director']->id)->subscribed('default'));

        // Paid for already: more seats is groups.seats.update.
        $this->as($c['director']);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 10, 'currency' => 'usd']), 'groups.seats.checkout', 409);

        // A newcomer joins and takes a free seat at once.
        $this->as(User::factory()->create(['name' => 'Ola']));
        $this->postJson('/api/choir/'.$c['token'], ['adult' => true])->assertOk()->assertJsonPath('data.seat', 'seated');
    }

    public function test_seats_change_in_number_and_move_at_the_directors_word(): void
    {
        $this->on();
        $names = array_map(static fn (int $i): string => 'Singer '.$i, range(1, 12));
        $c = $this->choir($names);

        // No paid seats yet: nothing to change or move.
        $this->assertMatchesContract($this->putJson('/api/groups/'.$c['id'].'/seats', ['seats' => 12]), 'groups.seats.update', 409);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/move', ['from' => $c['members']['Singer 1']->sqid(), 'to' => $c['members']['Singer 12']->sqid()]), 'groups.seats.move', 409);

        $this->seatsEvent('customer.subscription.created', $c['group'], 10)->assertOk();
        $seats = $this->seats($c['id']);
        $this->assertSame('seated', $seats['Singer 10']);
        $this->assertSame(['needed', 'needed'], [$seats['Singer 11'], $seats['Singer 12']]);

        // The director moves Singer 1's seat to Singer 12.
        $moved = $this->postJson('/api/groups/'.$c['id'].'/seats/move', ['from' => $c['members']['Singer 1']->sqid(), 'to' => $c['members']['Singer 12']->sqid()]);
        $this->assertMatchesContract($moved, 'groups.seats.move', 200);
        $seats = $this->seats($c['id']);
        $this->assertSame(['needed', 'seated'], [$seats['Singer 1'], $seats['Singer 12']]);
        // Not to someone seated, not from someone without one, not by a member, not in another group.
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/move', ['from' => $c['members']['Singer 2']->sqid(), 'to' => $c['members']['Singer 3']->sqid()]), 'groups.seats.move', 422);
        $this->postJson('/api/groups/'.$c['id'].'/seats/move', ['from' => $c['members']['Singer 1']->sqid(), 'to' => $c['members']['Singer 11']->sqid()])->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->assertMatchesContract($this->postJson('/api/groups/nope/seats/move', ['from' => $c['members']['Singer 2']->sqid(), 'to' => $c['members']['Singer 1']->sqid()]), 'groups.seats.move', 404);
        $this->as($c['members']['Singer 2']);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$c['id'].'/seats/move', ['from' => $c['members']['Singer 2']->sqid(), 'to' => $c['members']['Singer 1']->sqid()]), 'groups.seats.move', 403);
        $this->assertMatchesContract($this->putJson('/api/groups/'.$c['id'].'/seats', ['seats' => 12]), 'groups.seats.update', 403);
        $this->as($c['director']);

        // Two more seats: the two waiting, in join order.
        $this->assertMatchesContract($this->putJson('/api/groups/'.$c['id'].'/seats', ['seats' => 9]), 'groups.seats.update', 422);
        $this->assertMatchesContract($this->putJson('/api/groups/nope/seats', ['seats' => 12]), 'groups.seats.update', 404);
        $more = $this->putJson('/api/groups/'.$c['id'].'/seats', ['seats' => 12]);
        $this->assertMatchesContract($more, 'groups.seats.update', 200);
        $more->assertJsonPath('data.seats.bought', 12)->assertJsonPath('data.seats.used', 12);
        $this->assertSame([['sub_choir_'.$c['group']->id, 12]], $this->stripe->seatUpdates);

        // Fewer by the portal (Stripe's webhook): the seats given last go.
        $this->seatsEvent('customer.subscription.updated', $c['group'], 10)->assertOk();
        $seats = $this->seats($c['id']);
        $this->assertSame(10, count(array_filter($seats, static fn (string $s): bool => $s === 'seated')) - 1, 'ten members seated, and the director');
        $this->assertSame(['needed', 'needed'], [$seats['Singer 1'], $seats['Singer 11']], 'the last two seats given (Singer 11, then Singer 1) went');

        // A seat holder who gets their own Plus frees their seat for the first waiting, in join order, when it's next read.
        User::query()->whereKey($c['members']['Singer 3']->id)->update(['plan' => 'plus']);
        $this->as($c['members']['Singer 1']);
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.seat', 'seated');
        User::query()->whereKey($c['members']['Singer 3']->id)->update(['plan' => 'free']);
        $this->as($c['director']);
        $seats = $this->seats($c['id']);
        $this->assertSame(['seated', 'needed', 'needed'], [$seats['Singer 1'], $seats['Singer 3'], $seats['Singer 11']]);

        // A member who leaves frees their seat for the next waiting.
        $this->as($c['members']['Singer 2']);
        $this->deleteJson('/api/groups/'.$c['id'].'/members/'.$c['members']['Singer 2']->sqid())->assertNoContent();
        $this->as($c['director']);
        $seats = $this->seats($c['id']);
        $this->assertSame(['seated', 'needed'], [$seats['Singer 3'], $seats['Singer 11']], 'join order: Singer 3 before Singer 11');
    }

    public function test_when_the_seats_lapse_the_unseated_are_read_only_and_nothing_is_deleted(): void
    {
        $this->on();
        $c = $this->choir(['Sam', 'Kim']);
        $this->seatsEvent('customer.subscription.created', $c['group'], 10)->assertOk();

        // Seated, Sam practises the group's song and records on it; the director sees it.
        $this->as($c['members']['Sam']);
        $this->getJson('/api/templates/'.$c['song'])->assertOk();
        [$saved, $project] = $this->projectFrom($c['song']);
        $saved->assertOk();
        $part = (string) Str::uuid();
        $this->savePart($part, $project)->assertOk();
        $this->take($part)->assertCreated();

        // The seats end: Sam can't record in the group now; his seat and take stay.
        $this->seatsEvent('customer.subscription.deleted', $c['group'], 10, 'canceled')->assertOk();
        $refused = $this->take($part);
        $this->assertMatchesContract($refused, 'takes.create', 403);
        $refused->assertJsonPath('reason', 'seat_needed');
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.seat', 'needed');
        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'free');
        $this->assertSame(2, GroupSeat::query()->count(), 'the seats are kept for when they return');
        $this->as($c['director']);
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.seats.status', 'lapsed')->assertJsonPath('data.seats.bought', 0);

        // Bought again: the same singers are seated.
        $this->stripeEvent('customer.subscription.created', [
            'id' => 'sub_choir_again', 'customer' => User::query()->findOrFail($c['director']->id)->stripe_id, 'status' => 'active', 'trial_end' => null,
            'metadata' => ['type' => 'choir_'.$c['group']->id], 'items' => ['data' => [['id' => 'si_again', 'quantity' => 10, 'price' => ['id' => 'price_choir', 'product' => 'prod_choir']]]],
        ])->assertOk();
        $this->as($c['members']['Sam']);
        $this->take($part)->assertCreated();
    }

    public function test_groups_made_before_the_switch_get_fourteen_days_free_for_the_whole_choir(): void
    {
        // The choir trial is off since 2026-11-21; its code stays for a trial set again, so this runs it as it was.
        config(['billing.choir.trial_days' => 14]);
        $c = $this->choir(['Sam', 'Kim']);
        $this->travel(1)->minutes();
        $this->on();
        // A group made after the switch went on has no free days of its own.
        $this->as(User::factory()->create());
        $this->postJson('/api/groups', ['name' => 'New Choir', 'adult' => true])->assertCreated()->assertJsonPath('data.seats.status', 'none')->assertJsonPath('data.seat', 'needed');

        $this->as($c['director']);
        $panel = $this->getJson('/api/groups/'.$c['id'])->json('data.seats');
        $this->assertSame(['free_trial', 0, 0], [$panel['status'], $panel['bought'], $panel['used']]);
        $this->assertSame(['Ana Ruiz' => 'seated', 'Sam' => 'seated', 'Kim' => 'seated'], $this->seats($c['id']));
        $this->as($c['members']['Sam']);
        $this->getJson('/api/templates/'.$c['song'])->assertOk();
        $this->getJson('/api/billing')->assertJsonPath('data.plan', 'plus');

        // A first pack bought in the free days runs its trial to their end, not 14 more.
        $this->as($c['director']);
        $this->postJson('/api/groups/'.$c['id'].'/seats/checkout', ['seats' => 10, 'currency' => 'usd'])->assertCreated();
        $this->assertSame(14, (int) round(now()->diffInDays($this->stripe->seatCheckouts[0]['trialEnd'])));

        // Turning the switch off and on again gives no second trial.
        $this->travel(15)->days();
        app(Flags::class)->set('choir_seats', 'off', null, 'test');
        $this->on();
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.seats.status', 'none');
        $this->as($c['members']['Sam']);
        $this->getJson('/api/templates/'.$c['song'])->assertForbidden()->assertJsonPath('reason', 'seat_needed');
    }

    public function test_the_director_sees_no_progress_from_an_unseated_member(): void
    {
        $this->on();
        $c = $this->choir(['Sam']);
        $this->putJson('/api/groups/'.$c['id'].'/songs/amazing-grace/assignments', ['assignments' => [['memberId' => $c['members']['Sam']->sqid(), 'partKey' => 'alto']]])->assertOk();

        // Sam learns the library song alone (free), and records his part.
        $this->as($c['members']['Sam']);
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();

        $this->as($c['director']);
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.members.1.seat', 'needed')->assertJsonPath('data.members.1.assignments.0.status', 'none');

        $this->seatsEvent('customer.subscription.created', $c['group'], 10)->assertOk();
        $this->getJson('/api/groups/'.$c['id'])->assertJsonPath('data.members.1.seat', 'seated')->assertJsonPath('data.members.1.assignments.0.status', 'recorded');
    }

    public function test_a_deleted_group_stops_its_seats_billing(): void
    {
        $this->on();
        $c = $this->choir(['Sam']);
        $this->seatsEvent('customer.subscription.created', $c['group'], 10)->assertOk();
        $this->deleteJson('/api/groups/'.$c['id'])->assertNoContent();
        $this->assertSame(['sub_choir_'.$c['group']->id], $this->stripe->cancelled);
        $this->assertSame(0, GroupSeat::query()->count());

        // One choir trial a director: a new group's first pack gets no second 14 days.
        $id = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers Again', 'adult' => true])->assertCreated()->json('data.id');
        $this->postJson('/api/groups/'.$id.'/seats/checkout', ['seats' => 10, 'currency' => 'usd'])->assertCreated();
        $this->assertNull($this->stripe->seatCheckouts[0]['trialEnd']);
    }

    public function test_the_seat_counts_are_anonymous_kinds_the_app_may_send(): void
    {
        $this->postJson('/api/counts', ['events' => [['kind' => 'seats_offer_shown'], ['kind' => 'seats_offer_tap'], ['kind' => 'seat_ask_shown'], ['kind' => 'seats_bought_50']], 'signedIn' => true])->assertNoContent();
        $this->assertSame(['seat_ask_shown', 'seats_offer_shown', 'seats_offer_tap'], DB::table('usage_counts')->orderBy('kind')->pluck('kind')->all(), 'a pack bought is the server\'s alone');
    }

    public function test_the_seat_price_is_public(): void
    {
        $prices = $this->getJson('/api/billing/prices');
        $this->assertMatchesContract($prices, 'billing.prices', 200);
        $prices->assertJsonPath('data.seats.min', 10)->assertJsonPath('data.seats.trialDays', 0)->assertJsonPath('data.seats.prices.0', ['currency' => 'usd', 'amountCents' => 1500, 'taxInclusive' => false]);
    }

    public function test_seats_move_three_times_a_month_each_logged(): void
    {
        $this->on();
        $c = $this->choir(array_map(static fn (int $i): string => 'Singer '.$i, range(1, 12)));
        $this->seatsEvent('customer.subscription.created', $c['group'], 10)->assertOk();
        $move = fn (string $from, string $to) => $this->postJson('/api/groups/'.$c['id'].'/seats/move', ['from' => $c['members'][$from]->sqid(), 'to' => $c['members'][$to]->sqid()]);

        $move('Singer 1', 'Singer 11')->assertOk();
        $move('Singer 2', 'Singer 12')->assertOk();
        $move('Singer 11', 'Singer 1')->assertOk();
        $refused = $move('Singer 3', 'Singer 11');
        $this->assertMatchesContract($refused, 'groups.seats.move', 429);
        $this->assertGreaterThan(29 * 86400, (int) $refused->json('retryAfter'));
        $this->assertSame(3, AuditLog::query()->where('action', 'groups.seat_moved')->where('after->group', $c['group']->id)->count());

        $this->travel(31)->days();
        $move('Singer 3', 'Singer 11')->assertOk();
    }

    public function test_a_seat_covers_singing_with_the_group_never_ai_songs(): void
    {
        $this->on();
        $c = $this->choir(['Sam']);
        $this->seatsEvent('customer.subscription.created', $c['group'], 10)->assertOk();
        $sam = $c['members']['Sam']->fresh() ?? $c['members']['Sam'];

        $this->assertTrue(app(Plans::class)->isPlus($sam), 'A seat is Plus for the group.');
        $this->assertSame('free', OwnChoir::tier($sam), 'But AI songs need their own Plus.');
        $this->assertFalse(OwnChoir::quota($sam)['plus']);
    }

    public function test_staff_see_a_choirs_seats_and_move_or_refill_them_in_sudo_with_a_reason(): void
    {
        $this->on();
        $c = $this->choir();
        // A pack of 2 for 3 singers: Sam and Kim, who joined first, hold the seats; Lee has none.
        $this->seatsEvent('customer.subscription.created', $c['group'], 2)->assertOk();

        $admin = $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/groups/'.$c['id'].'/seats');
        $this->assertMatchesContract($shown, 'admin.groups.seats.show', 200);
        $shown->assertJsonPath('data.status', 'active')->assertJsonPath('data.bought', 2)->assertJsonPath('data.movesLeft', 3)->assertJsonPath('data.nextMoveAt', null);
        $this->assertSame(['Sam', 'Kim'], array_column((array) $shown->json('data.holders'), 'name'));
        $this->assertSame(['Lee'], array_column((array) $shown->json('data.unseated'), 'name'));
        $this->assertStringContainsString('subscriptions/sub_choir_'.$c['group']->id, (string) $shown->json('data.stripeUrl'));

        // Staff move Sam's seat to Lee: not counted against the director's three.
        $moved = $this->postJson('/api/admin/groups/'.$c['id'].'/seats', ['action' => 'move', 'from' => $c['members']['Sam']->sqid(), 'to' => $c['members']['Lee']->sqid(), 'reason' => 'Sam asked support to give it to Lee.']);
        $this->assertMatchesContract($moved, 'admin.groups.seats.update', 200);
        $this->assertSame(['Kim', 'Lee'], array_column((array) $moved->json('data.holders'), 'name'));
        $moved->assertJsonPath('data.movesLeft', 3);
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.groups.seat_moved')->where('actor_id', $admin->id)->count());

        $this->assertMatchesContract($this->postJson('/api/admin/groups/'.$c['id'].'/seats', ['action' => 'move', 'from' => $c['members']['Lee']->sqid(), 'to' => $c['members']['Kim']->sqid(), 'reason' => 'Again.']), 'admin.groups.seats.update', 422);
        $this->postJson('/api/admin/groups/'.$c['id'].'/seats', ['action' => 'sync'])->assertStatus(422);
        $this->postJson('/api/admin/groups/'.$c['id'].'/seats', ['action' => 'sync', 'reason' => 'Stripe changed the pack by hand.'])->assertOk();
        $this->assertMatchesContract($this->getJson('/api/admin/groups/nope/seats'), 'admin.groups.seats.show', 404);
        $this->assertMatchesContract($this->postJson('/api/admin/groups/nope/seats', ['action' => 'sync', 'reason' => 'Nothing.']), 'admin.groups.seats.update', 404);

        // Revenue shows the pack beside Plus.
        $revenue = $this->getJson('/api/admin/revenue');
        $this->assertMatchesContract($revenue, 'admin.revenue.show', 200);
        $this->assertSame([1, 2, 2], [$revenue->json('data.seats.choirs'), $revenue->json('data.seats.bought'), $revenue->json('data.seats.used')]);

        // A director's own moves are counted: after three, Admin says when the next may be.
        foreach (range(1, 3) as $i) {
            AuditLog::record('groups.seat_moved', null, $c['director'], after: ['group' => $c['group']->id, 'from' => 'a', 'to' => 'b']);
        }

        $this->getJson('/api/admin/groups/'.$c['id'].'/seats')->assertJsonPath('data.movesLeft', 0)->assertJsonPath('data.movesThisMonth', 3);
        $this->assertNotNull($this->getJson('/api/admin/groups/'.$c['id'].'/seats')->json('data.nextMoveAt'));
    }
}
