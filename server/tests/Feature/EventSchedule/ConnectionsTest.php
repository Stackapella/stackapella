<?php

declare(strict_types=1);

namespace Tests\Feature\EventSchedule;

use App\Models\Engagement;
use App\Models\EsConnection;
use App\Models\LessonBooking;
use App\Models\User;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\EventScheduleError;
use App\Services\EventSchedule\HttpEventSchedule;
use App\Support\Http\PublicAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EventScheduling;
use Tests\TestCase;

/**
 * Connecting an account to Event Schedule (contract/api.md, "Event Schedule together (M9)").
 */
final class ConnectionsTest extends TestCase
{
    use EventScheduling;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConnect();
        $this->fakeEventSchedule();
    }

    public function test_someone_connects_by_oauth_picks_a_schedule_and_the_webhook_is_registered(): void
    {
        $user = $this->as(User::factory()->create(['name' => 'Grace Okafor']));
        $this->assertMatchesContract($this->getJson('/api/event-schedule/connection'), 'eventSchedule.connection.show', 200);
        $this->getJson('/api/event-schedule/connection')->assertJsonPath('data', null);

        $started = $this->postJson('/api/event-schedule/connect');
        $this->assertMatchesContract($started, 'eventSchedule.connect.create', 200);
        parse_str((string) parse_url((string) $started->json('data.url'), PHP_URL_QUERY), $back);
        $this->assertStringStartsWith('https://stackapella.test/connect/event-schedule/callback?', (string) $started->json('data.url'));

        // Somebody else can't finish it, and a state is good once.
        $this->as(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect/finish', ['code' => $back['code'], 'state' => $back['state']]), 'eventSchedule.connect.finish', 410);
        $this->as($user);
        $again = $this->postJson('/api/event-schedule/connect');
        parse_str((string) parse_url((string) $again->json('data.url'), PHP_URL_QUERY), $back);
        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect/finish', ['state' => $back['state']]), 'eventSchedule.connect.finish', 422);

        // Event Schedule down for the token exchange: try again later, with the same state.
        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect/finish', ['code' => $back['code'], 'state' => $back['state']]), 'eventSchedule.connect.finish', 424);

        $finished = $this->postJson('/api/event-schedule/connect/finish', ['code' => $back['code'], 'state' => $back['state']]);
        $this->assertMatchesContract($finished, 'eventSchedule.connect.finish', 200);
        $finished->assertJsonPath('data.status', 'active')->assertJsonPath('data.schedule', null)->assertJsonPath('data.account.name', 'Grace Okafor')->assertJsonMissingPath('data.accessToken');
        $this->assertSame(410, $this->postJson('/api/event-schedule/connect/finish', ['code' => $back['code'], 'state' => $back['state']])->status());

        // The tokens are kept encrypted, never in plain text.
        $raw = (array) DB::table('es_connections')->where('user_id', $user->id)->first();
        $this->assertStringNotContainsString('fake-at-', (string) $raw['access_token']);
        $this->assertStringStartsWith('fake-at-', (string) EsConnection::query()->firstOrFail()->access_token);

        $schedules = $this->getJson('/api/event-schedule/schedules');
        $this->assertMatchesContract($schedules, 'eventSchedule.schedules.list', 200);
        $this->assertMatchesContract($this->patchJson('/api/event-schedule/connection', ['schedule' => 'not-mine']), 'eventSchedule.connection.update', 422);

        $picked = $this->patchJson('/api/event-schedule/connection', ['schedule' => 'grace-sings']);
        $this->assertMatchesContract($picked, 'eventSchedule.connection.update', 200);
        $picked->assertJsonPath('data.schedule.subdomain', 'grace-sings')->assertJsonPath('data.webhook', 'active');
        $connection = EsConnection::query()->firstOrFail();
        $hook = $this->es->state()['webhooks'][(string) $connection->webhook_id];
        $this->assertSame('https://stackapella.test/webhooks/event-schedule/'.$connection->sqid(), $hook['url']);
        $this->assertSame($hook['secret'], $connection->webhook_secret);

        // Picking another moves the webhook.
        $this->patchJson('/api/event-schedule/connection', ['schedule' => 'riverside-choir'])->assertOk();
        $this->assertCount(1, $this->es->state()['webhooks']);

        $types = $this->getJson('/api/event-schedule/appointment-types');
        $this->assertMatchesContract($types, 'eventSchedule.types.list', 200);
        $types->assertJsonPath('data.0.id', 'Ap45')->assertJsonPath('data.1.requiresApproval', true)->assertJsonPath('data.0.linked', null);
    }

    public function test_a_connection_es_stops_accepting_needs_reconnecting_and_reconnecting_keeps_the_schedule(): void
    {
        $user = $this->as(User::factory()->create());
        $connection = $this->esConnection($user);

        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->getJson('/api/event-schedule/schedules'), 'eventSchedule.schedules.list', 424);
        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->getJson('/api/event-schedule/appointment-types'), 'eventSchedule.types.list', 424);
        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->patchJson('/api/event-schedule/connection', ['schedule' => 'riverside-choir']), 'eventSchedule.connection.update', 424);

        $this->es->failNext(EventScheduleError::RECONNECT);
        $this->assertMatchesContract($this->getJson('/api/event-schedule/schedules'), 'eventSchedule.schedules.list', 409);
        $this->assertSame(EsConnection::NEEDS_RECONNECT, $connection->refresh()->status);
        $this->assertMatchesContract($this->getJson('/api/event-schedule/appointment-types'), 'eventSchedule.types.list', 409);
        $this->assertMatchesContract($this->patchJson('/api/event-schedule/connection', ['schedule' => 'grace-sings']), 'eventSchedule.connection.update', 409);
        $this->getJson('/api/event-schedule/connection')->assertJsonPath('data.status', 'needs_reconnect');

        $started = $this->postJson('/api/event-schedule/connect');
        parse_str((string) parse_url((string) $started->json('data.url'), PHP_URL_QUERY), $back);
        $this->postJson('/api/event-schedule/connect/finish', ['code' => $back['code'], 'state' => $back['state']])->assertOk()
            ->assertJsonPath('data.status', 'active');

        // The fake's account id for this person differs from the factory's: a different account.
        $this->assertNull($connection->refresh()->schedule);
        $connection->forceFill(['schedule' => 'grace-sings', 'webhook_id' => 'Wh1'])->save();
        $started = $this->postJson('/api/event-schedule/connect');
        parse_str((string) parse_url((string) $started->json('data.url'), PHP_URL_QUERY), $back);
        $this->postJson('/api/event-schedule/connect/finish', ['code' => $back['code'], 'state' => $back['state']])->assertOk()->assertJsonPath('data.schedule.subdomain', 'grace-sings');
    }

    public function test_nobody_connects_by_oauth_while_es_isnt_set_up(): void
    {
        $this->app->instance(EventSchedule::class, new HttpEventSchedule);
        $this->as(User::factory()->create());

        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect'), 'eventSchedule.connect.create', 423);
        foreach (['eventSchedule.connection.update' => ['PATCH', '/api/event-schedule/connection'], 'eventSchedule.connection.delete' => ['DELETE', '/api/event-schedule/connection'], 'eventSchedule.schedules.list' => ['GET', '/api/event-schedule/schedules'], 'eventSchedule.types.list' => ['GET', '/api/event-schedule/appointment-types']] as $operation => [$method, $path]) {
            $this->assertMatchesContract($this->json($method, $path, $method === 'PATCH' ? ['schedule' => 'x'] : []), $operation, 404);
        }
    }

    public function test_a_selfhosted_install_connects_with_a_public_https_address_and_a_key_es_accepts(): void
    {
        $this->as(User::factory()->create());

        foreach (['http://events.example.org', 'https://127.0.0.1', 'https://localhost', 'https://169.254.169.254', 'https://user:pw@events.example.org'] as $bad) {
            $this->assertMatchesContract($this->postJson('/api/event-schedule/connect/key', ['baseUrl' => $bad, 'apiKey' => 'es-abcdefgh']), 'eventSchedule.connect.key', 422);
        }

        PublicAddress::$resolver = fn (string $host): array => [];
        $this->postJson('/api/event-schedule/connect/key', ['baseUrl' => 'https://nowhere.example.org', 'apiKey' => 'es-abcdefgh'])->assertStatus(422)->assertJsonValidationErrors('baseUrl');
        $this->assertDatabaseCount('es_connections', 0);
    }

    public function test_a_selfhosted_key_is_checked_with_es_and_a_refused_one_changes_nothing(): void
    {
        PublicAddress::$resolver = fn (string $host): array => $host === 'events.example.org' ? ['93.184.216.34'] : ['10.0.0.5'];
        $user = $this->as(User::factory()->create());

        $this->postJson('/api/event-schedule/connect/key', ['baseUrl' => 'https://inside.example.org', 'apiKey' => 'es-abcdefgh'])->assertStatus(422)->assertJsonValidationErrors('baseUrl');
        $this->postJson('/api/event-schedule/connect/key', ['baseUrl' => 'https://events.example.org', 'apiKey' => 'wrong-key-1'])->assertStatus(422)->assertJsonValidationErrors('apiKey');
        $this->assertDatabaseCount('es_connections', 0);

        $made = $this->postJson('/api/event-schedule/connect/key', ['baseUrl' => 'https://events.example.org/', 'apiKey' => 'es-abcdefgh']);
        $this->assertMatchesContract($made, 'eventSchedule.connect.key', 200);
        $made->assertJsonPath('data.mode', 'api_key')->assertJsonPath('data.baseUrl', 'https://events.example.org');

        // A wrong key later leaves the working one in place.
        $this->postJson('/api/event-schedule/connect/key', ['baseUrl' => 'https://events.example.org', 'apiKey' => 'wrong-key-2'])->assertStatus(422);
        $this->assertSame('es-abcdefgh', EsConnection::query()->where('user_id', $user->id)->firstOrFail()->access_token);
    }

    protected function tearDown(): void
    {
        PublicAddress::$resolver = null;

        parent::tearDown();
    }

    public function test_connecting_is_limited(): void
    {
        $this->as(User::factory()->create());

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/event-schedule/connect')->assertOk();
        }

        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect'), 'eventSchedule.connect.create', 429);
        $this->assertMatchesContract($this->postJson('/api/event-schedule/connect/key', ['baseUrl' => 'https://events.example.org', 'apiKey' => 'es-abcdefgh']), 'eventSchedule.connect.key', 429);
    }

    public function test_disconnecting_revokes_and_keeps_the_webhook_only_while_booked_lessons_are_to_come(): void
    {
        $user = $this->as(User::factory()->create());
        $connection = $this->esConnection($user);
        $this->es->registerWebhook($connection, 'https://x', 'grace-sings');

        // A lesson still to come: the connection stays, tokenless, for its webhook.
        $engagement = Engagement::factory()->create(['kind' => Engagement::LESSON, 'status' => Engagement::BOOKED, 'coach_id' => $user->id]);
        $lesson = LessonBooking::factory()->create(['engagement_id' => $engagement->id, 'es_connection_id' => $connection->id]);

        $this->deleteJson('/api/event-schedule/connection')->assertNoContent();
        $connection->refresh();
        $this->assertSame(EsConnection::DISCONNECTED, $connection->status);
        $this->assertNull($connection->access_token);
        $this->assertNotNull($connection->webhook_secret);
        $this->assertContains('revoke', $this->es->state()['calls']);
        $this->getJson('/api/event-schedule/connection')->assertJsonPath('data', null);
        $this->assertMatchesContract($this->deleteJson('/api/event-schedule/connection'), 'eventSchedule.connection.delete', 404);

        // Once it's past, the reconcile lets the connection go.
        $lesson->forceFill(['status' => LessonBooking::COMPLETED])->save();
        $this->artisan('app:event-schedule:reconcile')->assertSuccessful();
        $this->assertDatabaseCount('es_connections', 0);

        // With nothing booked, it goes at once, its webhook with it.
        $again = $this->esConnection($user);
        $this->deleteJson('/api/event-schedule/connection')->assertNoContent();
        $this->assertDatabaseMissing('es_connections', ['id' => $again->id]);
        $this->assertContains('deleteWebhook', $this->es->state()['calls']);
    }
}
