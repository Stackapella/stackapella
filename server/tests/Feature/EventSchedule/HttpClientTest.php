<?php

declare(strict_types=1);

namespace Tests\Feature\EventSchedule;

use App\Models\EsConnection;
use App\Services\EventSchedule\EventScheduleError;
use App\Services\EventSchedule\HttpEventSchedule;
use App\Services\EventSchedule\Signature;
use App\Support\Http\PublicAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The real Event Schedule client, against ES's API as contract/event-schedule.md describes it.
 */
final class HttpClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.event_schedule.url' => 'https://es.test', 'services.event_schedule.client_id' => 'stackapella', 'services.event_schedule.client_secret' => 'shh']);
    }

    public function test_it_asks_for_pkce_and_the_scopes_and_books_as_already_paid_with_an_idempotency_key(): void
    {
        $client = new HttpEventSchedule;
        $this->assertTrue($client->configured());
        parse_str((string) parse_url($client->authorizeUrl('st', 'ch'), PHP_URL_QUERY), $query);
        $this->assertSame(['code', 'stackapella', 'https://stackapella.test/connect/event-schedule/callback', 'st', 'ch', 'S256'], [$query['response_type'], $query['client_id'], $query['redirect_uri'], $query['state'], $query['code_challenge'], $query['code_challenge_method']]);
        $this->assertStringContainsString('appointments:write', (string) $query['scope']);

        Http::fake(['https://es.test/api/appointment-types/Ap45/bookings' => Http::response(['data' => ['id' => 'Bk1', 'event_id' => 'Ev1', 'status' => 'confirmed', 'starts_at' => '2026-11-03T18:00:00Z', 'ends_at' => '2026-11-03T18:45:00Z', 'timezone' => 'Europe/London', 'manage_url' => 'https://es.test/m', 'ical_url' => 'https://es.test/i', 'location_url' => 'https://meet.test/x', 'external_id' => 'En1']], 201)]);
        $connection = EsConnection::factory()->create(['base_url' => 'https://es.test']);

        $booked = $client->book($connection, 'Ap45', ['externalId' => 'En1', 'slot' => '2026-11-03T18:00:00Z', 'name' => 'Ana', 'email' => 'ana@example.com', 'guestTimezone' => 'UTC', 'notes' => null], 'lesson-book-1');

        $this->assertSame(['Bk1', 'confirmed', 'https://meet.test/x'], [$booked['id'], $booked['status'], $booked['locationUrl']]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', 'lesson-book-1') && $request->hasHeader('Authorization', 'Bearer '.$connection->access_token) && $request['payment'] === 'external' && $request['external_id'] === 'En1');
    }

    public function test_a_taken_slot_and_es_being_down_are_told_apart(): void
    {
        $connection = EsConnection::factory()->create(['base_url' => 'https://es.test']);
        Http::fake(['https://es.test/api/appointment-types/A/bookings' => Http::response(['error' => 'slot_taken'], 409), 'https://es.test/api/appointment-types/B/bookings' => Http::response('', 503)]);
        $booking = ['externalId' => 'En1', 'slot' => '2026-11-03T18:00:00Z', 'name' => 'Ana', 'email' => 'a@example.com', 'guestTimezone' => 'UTC', 'notes' => null];

        foreach (['A' => EventScheduleError::SLOT_TAKEN, 'B' => EventScheduleError::UNAVAILABLE] as $type => $reason) {
            try {
                (new HttpEventSchedule)->book($connection, $type, $booking, 'k');
                $this->fail('It should have failed.');
            } catch (EventScheduleError $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
    }

    public function test_an_expired_token_is_refreshed_once_and_a_refused_refresh_needs_a_reconnect(): void
    {
        $connection = EsConnection::factory()->create(['base_url' => 'https://es.test', 'access_token' => 'old', 'refresh_token' => 'rt-old', 'expires_at' => now()->subMinute()]);
        Http::fake([
            'https://es.test/oauth/token' => Http::response(['access_token' => 'new', 'refresh_token' => 'rt-new', 'expires_in' => 3600, 'token_type' => 'Bearer', 'scope' => 'profile schedules:read']),
            'https://es.test/api/schedules' => Http::response(['data' => [['subdomain' => 'grace', 'name' => 'Grace', 'type' => 'talent', 'timezone' => 'Europe/London']]]),
        ]);

        $this->assertSame('grace', (new HttpEventSchedule)->schedules($connection)[0]['subdomain']);
        $connection->refresh();
        $this->assertSame(['new', 'rt-new'], [$connection->access_token, $connection->refresh_token]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://es.test/oauth/token' && $request['refresh_token'] === 'rt-old' && $request['client_secret'] === 'shh');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://es.test/api/schedules' && $request->hasHeader('Authorization', 'Bearer new'));

        // ES revoked it: a 401, a refused refresh, and the connection says so.
        config(['services.event_schedule.url' => 'https://es2.test']);
        $connection->forceFill(['base_url' => 'https://es2.test'])->save();
        Http::fake([
            'https://es2.test/api/schedules' => Http::response(['message' => 'Unauthenticated.'], 401),
            'https://es2.test/oauth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        try {
            (new HttpEventSchedule)->schedules($connection);
            $this->fail('It should need reconnecting.');
        } catch (EventScheduleError $e) {
            $this->assertSame(EventScheduleError::RECONNECT, $e->reason);
        }

        $this->assertSame([EsConnection::NEEDS_RECONNECT, 'invalid_grant'], [$connection->refresh()->status, $connection->last_error]);
    }

    public function test_the_v2_signature_covers_the_time_and_the_body(): void
    {
        $now = 1_800_000_000;
        $header = Signature::make('secret', '{"a":1}', $now);

        $this->assertTrue(Signature::valid('secret', '{"a":1}', $header, $now + 299));
        $this->assertFalse(Signature::valid('secret', '{"a":1}', $header, $now + 301));
        $this->assertFalse(Signature::valid('secret', '{"a":2}', $header, $now));
        $this->assertFalse(Signature::valid('other', '{"a":1}', $header, $now));
        $this->assertFalse(Signature::valid('', '{"a":1}', $header, $now));
        $this->assertTrue(Signature::valid('secret', '{"a":1}', 't='.$now.',v2=nope,'.explode(',', $header)[1], $now));
    }

    public function test_a_selfhosted_address_must_be_public_and_is_pinned_to_the_address_checked(): void
    {
        foreach (['93.184.216.34' => true, '2606:4700::1111' => true, '10.1.2.3' => false, '100.64.1.1' => false, '169.254.169.254' => false, '::ffff:127.0.0.1' => false, '64:ff9b::a00:1' => false, 'fd00::1' => false] as $ip => $public) {
            $this->assertSame($public, PublicAddress::isPublic($ip), $ip);
        }

        PublicAddress::$resolver = fn (string $host): array => ['93.184.216.34'];
        $this->assertSame(['host' => 'events.example.org', 'port' => 443, 'ip' => '93.184.216.34'], PublicAddress::resolve('https://events.example.org'));
        $this->assertFalse(PublicAddress::resolve('https://events.example.org:22'));
        PublicAddress::$resolver = null;
    }
}
