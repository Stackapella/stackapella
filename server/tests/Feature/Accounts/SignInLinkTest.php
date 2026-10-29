<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Enums\BlockType;
use App\Mail\Account\SignInLinkMail;
use App\Models\Block;
use App\Models\SignInLink;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sign in by a link sent by email (contract/api.md, auth.link.send and auth.link.open): always the
 * same answer, 15 minutes and once, an account made for a new address, its name and age asked
 * after, and where to go next.
 */
final class SignInLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * The token in the last link emailed to that address.
     */
    private function tokenSentTo(string $email): string
    {
        $mail = Mail::queued(SignInLinkMail::class, fn (SignInLinkMail $m): bool => $m->hasTo($email))->last();
        $this->assertNotNull($mail, "No sign-in link went to {$email}.");

        return $mail->token;
    }

    public function test_a_new_address_gets_a_link_that_makes_its_account_and_asks_its_name_and_age_after(): void
    {
        $sent = $this->postJson('/api/auth/link', ['email' => 'Ana@Example.com', 'intended' => '/choir/abc123']);
        $this->assertMatchesContract($sent, 'auth.link.send', 202);
        $token = $this->tokenSentTo('ana@example.com');
        $this->assertFalse(User::query()->where('email', 'ana@example.com')->exists());
        $this->assertSame(hash('sha256', $token), SignInLink::query()->sole()->token_hash);

        $opened = $this->postJson('/api/auth/link/open', ['token' => $token]);
        $this->assertMatchesContract($opened, 'auth.link.open', 200);
        $opened->assertJsonPath('created', true)->assertJsonPath('intended', '/choir/abc123')->assertJsonPath('needsProfile', true)
            ->assertJsonPath('data.email', 'ana@example.com')->assertJsonPath('data.emailVerified', true);
        $this->assertAuthenticated();

        // A second tap: used, said as such.
        $this->assertMatchesContract($this->postJson('/api/auth/link/open', ['token' => $token]), 'auth.link.open', 410);
        $this->postJson('/api/auth/link/open', ['token' => $token])->assertJsonPath('code', 'link_used');
    }

    public function test_an_existing_account_is_signed_in_and_the_answer_never_says_which(): void
    {
        $kim = User::factory()->create(['email' => 'kim@example.com', 'name' => 'Kim', 'birth_month' => 5, 'birth_year' => 1990]);
        $known = $this->postJson('/api/auth/link', ['email' => 'kim@example.com', 'intended' => 'https://evil.example/steal']);
        $unknown = $this->postJson('/api/auth/link', ['email' => 'nobody-yet@example.com']);
        $this->assertSame($known->json(), $unknown->json());
        $this->assertSame($known->status(), $unknown->status());

        $opened = $this->postJson('/api/auth/link/open', ['token' => $this->tokenSentTo('kim@example.com')]);
        // Another site is never where it goes.
        $opened->assertOk()->assertJsonPath('created', false)->assertJsonPath('intended', '/')->assertJsonPath('needsProfile', false);
        $this->assertAuthenticatedAs($kim);
    }

    public function test_a_link_lasts_15_minutes_and_sends_are_limited_per_address(): void
    {
        $this->postJson('/api/auth/link', ['email' => 'lee@example.com'])->assertStatus(202);
        $token = $this->tokenSentTo('lee@example.com');
        $this->travel(16)->minutes();
        $this->postJson('/api/auth/link/open', ['token' => $token])->assertStatus(410)->assertJsonPath('code', 'link_expired');
        $this->postJson('/api/auth/link/open', ['token' => 'not-a-token'])->assertStatus(410)->assertJsonPath('code', 'link_expired');

        // A minute's worth to one address: the rest answer the same and send nothing.
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/auth/link', ['email' => 'sam@example.com'])->assertStatus(202);
        }
        Mail::assertQueued(SignInLinkMail::class, fn (SignInLinkMail $m): bool => $m->hasTo('sam@example.com'), 1);
    }

    public function test_paused_sign_ups_send_nothing_to_a_new_address_and_suspended_accounts_get_nothing(): void
    {
        app(Flags::class)->set('pause_signups', 'all', null, 'Paused');
        $this->postJson('/api/auth/link', ['email' => 'new@example.com'])->assertStatus(202);
        Mail::assertNotQueued(SignInLinkMail::class);

        User::factory()->suspended()->create(['email' => 'gone@example.com']);
        $this->postJson('/api/auth/link', ['email' => 'gone@example.com'])->assertStatus(202);
        Mail::assertNotQueued(SignInLinkMail::class);

        // An account that exists still gets its link while sign-ups are paused.
        User::factory()->create(['email' => 'ada@example.com']);
        $this->postJson('/api/auth/link', ['email' => 'ada@example.com'])->assertStatus(202);
        Mail::assertQueued(SignInLinkMail::class, 1);
    }

    public function test_a_sign_up_someone_else_made_with_this_address_starts_fresh_when_its_owner_opens_a_link(): void
    {
        // Someone signs up with Ana's address and a password they know; she never confirms it.
        $this->postJson('/api/auth/register', ['name' => 'Not Ana', 'email' => 'ana@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 1, 'birthYear' => 1990, 'elapsedMs' => 45000, 'nickname' => ''])->assertStatus(202);

        // Later (the address's limits count the verification email too).
        $this->travel(5)->minutes();
        $this->postJson('/api/auth/link', ['email' => 'ana@example.com'])->assertStatus(202);
        $opened = $this->postJson('/api/auth/link/open', ['token' => $this->tokenSentTo('ana@example.com')]);
        $opened->assertOk()->assertJsonPath('created', false)->assertJsonPath('needsProfile', true)->assertJsonPath('data.name', '')->assertJsonPath('data.birthGiven', false);

        // Their password no longer opens it.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->postJson('/api/auth/sign-in', ['email' => 'ana@example.com', 'password' => 'a-fine-long-passphrase-42'])->assertStatus(401);
        $this->assertNull(User::query()->sole()->password);
    }

    public function test_empty_quiet_hours_are_refused_not_a_crash(): void
    {
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->patchJson('/api/account', ['quietHours' => []])->assertStatus(422)->assertJsonValidationErrors('quietHours');
    }

    public function test_a_blocked_address_gets_no_link_and_a_suspension_after_sending_stops_the_link_working(): void
    {
        Block::factory()->create(['type' => BlockType::EmailDomain, 'value' => 'example.net']);
        $this->assertMatchesContract($this->postJson('/api/auth/link', ['email' => 'spam@example.net']), 'auth.link.send', 403);
        Mail::assertNotQueued(SignInLinkMail::class);

        $ada = User::factory()->create(['email' => 'ada@example.com']);
        $this->postJson('/api/auth/link', ['email' => 'ada@example.com'])->assertStatus(202);
        $token = $this->tokenSentTo('ada@example.com');
        $ada->forceFill(['suspended_at' => now()])->save();

        $this->assertMatchesContract($this->postJson('/api/auth/link/open', ['token' => $token]), 'auth.link.open', 403);
        $this->assertGuest();
    }
}
