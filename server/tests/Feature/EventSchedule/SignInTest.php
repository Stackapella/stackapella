<?php

declare(strict_types=1);

namespace Tests\Feature\EventSchedule;

use App\Models\User;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\FakeEventSchedule;
use App\Services\EventSchedule\HttpEventSchedule;
use App\Support\Accounts\SignInSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sign in with Event Schedule (contract/api.md, "Books, posts and signing in with Event Schedule
 * (M10)"): matched on the Event Schedule account, never by email.
 */
final class SignInTest extends TestCase
{
    use RefreshDatabase;

    private FakeEventSchedule $es;

    protected function setUp(): void
    {
        parent::setUp();

        $this->es = new FakeEventSchedule;
        $this->app->instance(EventSchedule::class, $this->es);
    }

    /**
     * Start, and follow Event Schedule's answer back to our callback.
     *
     * @param  array<string, string>  $with
     */
    private function signIn(array $with = []): TestResponse
    {
        $started = $this->post('/auth/event-schedule', ['intended' => '/settings', ...$with]);
        $started->assertRedirect();
        $back = (string) $started->headers->get('Location');
        $this->assertStringStartsWith('https://stackapella.test/auth/event-schedule/callback?', $back);

        return $this->get(substr($back, strlen('https://stackapella.test')));
    }

    public function test_a_first_sign_in_makes_an_account_matched_on_the_es_account_only(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('data.methods', ['apple', 'google', 'event_schedule', 'password']);

        $this->signIn()->assertRedirect('/settings?signedIn=event_schedule');
        $user = User::query()->sole();
        $this->assertNotNull($user->event_schedule_id);
        $this->assertNull($user->email);
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/account')->assertJsonPath('data.methods.eventSchedule', true);
        $this->assertContains('revoke', $this->es->state()['calls']);

        // Again: the same account.
        $this->post('/api/auth/sign-out');
        $this->signIn()->assertRedirect('/settings?signedIn=event_schedule');
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_stale_state_or_a_no_goes_back_with_a_reason_and_it_waits_while_es_isnt_set_up(): void
    {
        $this->post('/auth/event-schedule', ['intended' => '/settings'])->assertRedirect();
        $this->get('/auth/event-schedule/callback?code=fake-x&state=not-ours')->assertRedirect('/settings?authError=expired');
        $this->post('/auth/event-schedule', ['intended' => '/settings'])->assertRedirect();
        $this->get('/auth/event-schedule/callback?error=access_denied&state=x')->assertRedirect('/settings?authError=cancelled');
        $this->assertSame(0, User::query()->count());
    }

    public function test_it_waits_while_es_isnt_set_up(): void
    {
        $this->app->instance(EventSchedule::class, new HttpEventSchedule);

        $this->post('/auth/event-schedule', ['intended' => '/settings'])->assertRedirect('/settings?authError=unavailable');
        $this->getJson('/api/auth/config')->assertJsonPath('data.methods', ['apple', 'google', 'password']);
    }

    public function test_it_can_be_added_to_an_account_and_removed_from_one(): void
    {
        $user = User::factory()->withPassword()->create();
        $this->actingAs($user)->withSession([SignInSession::SIGNED_IN_AT => now()->getTimestamp()]);

        $this->signIn(['link' => '1'])->assertRedirect('/settings?linked=event_schedule');
        $this->assertNotNull($user->refresh()->event_schedule_id);
        $this->assertSame(1, User::query()->count());

        $removed = $this->deleteJson('/api/account/methods/event-schedule');
        $this->assertMatchesContract($removed, 'account.methods.delete', 200);
        $removed->assertJsonPath('data.methods.eventSchedule', false);
        $this->assertNull($user->refresh()->event_schedule_id);
    }
}
