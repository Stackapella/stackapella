<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Enums\BlockType;
use App\Enums\SecurityEventType;
use App\Mail\Account\AlreadyHaveAnAccount;
use App\Mail\Account\VerifyEmailAddress;
use App\Models\Block;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Flags;
use App\Support\Trust\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SignUpTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-fine-long-passphrase-42';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function form(array $with = []): array
    {
        return ['name' => 'Ada Lovelace', 'email' => 'Ada@Example.com', 'password' => self::PASSWORD, 'birthMonth' => 5, 'birthYear' => 1990, 'ref' => 'hn', 'elapsedMs' => 45000, 'nickname' => '', ...$with];
    }

    private function verifyToken(string $email): string
    {
        $token = null;
        Mail::assertQueued(VerifyEmailAddress::class, function (VerifyEmailAddress $mail) use ($email, &$token): bool {
            $token = $mail->token;

            return $mail->hasTo($email);
        });

        return (string) $token;
    }

    public function test_the_config_says_what_the_sign_in_screens_need(): void
    {
        $response = $this->getJson('/api/auth/config');

        $this->assertMatchesContract($response, 'auth.config', 200);
        $response->assertJsonPath('data.signups', 'open')->assertJsonPath('data.turnstileSiteKey', null)->assertJsonPath('data.passwordMinLength', 10);

        config(['services.turnstile.site_key' => 'site-key']);
        app(Flags::class)->set('shield_mode', 'on', null, 'An attack');
        app(Flags::class)->set('pause_signups', 'password', null, 'Bots');

        $this->getJson('/api/auth/config')->assertJsonPath('data.signups', 'password_paused')->assertJsonPath('data.turnstileSiteKey', 'site-key');

        // The browser error reporter's DSN, only once it's set.
        $this->getJson('/api/auth/config')->assertJsonPath('data.sentryDsn', null);
        config(['sentry.browser_dsn' => 'https://key@o1.ingest.sentry.io/2']);
        $this->assertMatchesContract($this->getJson('/api/auth/config')->assertJsonPath('data.sentryDsn', 'https://key@o1.ingest.sentry.io/2'), 'auth.config', 200);
    }

    public function test_signing_up_sends_a_link_that_verifies_and_signs_in_once(): void
    {
        $response = $this->postJson('/api/auth/register', $this->form());

        $this->assertMatchesContract($response, 'auth.register', 202);
        $this->assertGuest();
        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('hn', $user->ref);
        $this->assertNotNull($user->age_confirmed_at);
        $this->assertSame(1, SecurityEvent::query()->where('type', SecurityEventType::SignUp)->count());

        $token = $this->verifyToken('ada@example.com');
        $verified = $this->postJson('/api/auth/verify', ['token' => $token]);

        $this->assertMatchesContract($verified, 'auth.verify', 200);
        $verified->assertJsonPath('data.emailVerified', true)->assertJsonPath('data.methods.password', true)->assertJsonPath('data.handle', null);
        $this->assertAuthenticatedAs($user);

        $this->assertMatchesContract($this->postJson('/api/auth/verify', ['token' => $token]), 'auth.verify', 410);
    }

    public function test_an_address_with_an_account_gets_the_same_answer_and_an_email_not_a_second_account(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->assertMatchesContract($this->postJson('/api/auth/register', $this->form()), 'auth.register', 202);

        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
        Mail::assertQueued(AlreadyHaveAnAccount::class, fn (AlreadyHaveAnAccount $mail): bool => $mail->hasTo('ada@example.com'));
        Mail::assertNotQueued(VerifyEmailAddress::class);
    }

    public function test_signing_up_over_an_unverified_account_drops_its_unproven_password(): void
    {
        // Someone signed up first with Ada's address and a password of their own.
        $squatter = User::factory()->withPassword('the-squatters-password')->unverified()->create(['email' => 'ada@example.com']);

        $this->assertMatchesContract($this->postJson('/api/auth/register', $this->form()), 'auth.register', 202);

        $this->assertNull($squatter->refresh()->password);
        Mail::assertQueued(AlreadyHaveAnAccount::class, fn (AlreadyHaveAnAccount $mail): bool => $mail->hasTo('ada@example.com'));
        Mail::assertNotQueued(VerifyEmailAddress::class);
        $this->postJson('/api/auth/sign-in', ['email' => 'ada@example.com', 'password' => 'the-squatters-password'])->assertStatus(401);
    }

    public function test_bots_get_the_same_answer_and_nothing_happens(): void
    {
        $this->assertMatchesContract($this->postJson('/api/auth/register', $this->form(['nickname' => 'bot'])), 'auth.register', 202);
        $this->assertMatchesContract($this->postJson('/api/auth/register', $this->form(['elapsedMs' => 800])), 'auth.register', 202);

        $this->assertSame(0, User::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_the_form_is_checked(): void
    {
        $response = $this->postJson('/api/auth/register', $this->form(['password' => 'qwertyuiop', 'birthMonth' => 13, 'name' => 'visit spam.com', 'ref' => 'no spaces']));

        $this->assertMatchesContract($response, 'auth.register', 422);
        $response->assertJsonValidationErrors(['password', 'birthMonth', 'name', 'ref']);
        $this->assertMatchesContract($this->postJson('/api/auth/register', $this->form(['password' => 'short'])), 'auth.register', 422);
    }

    public function test_blocked_paused_and_shielded_sign_ups_are_refused(): void
    {
        Block::factory()->create(['type' => BlockType::EmailDomain, 'value' => 'example.com']);
        $this->assertMatchesContract($this->postJson('/api/auth/register', $this->form()), 'auth.register', 403);
        Block::query()->delete();

        app(Flags::class)->set('pause_signups', 'password', null, 'Bots');
        // Refused before validation: even an empty form gets the pause.
        $this->assertMatchesContract($this->postJson('/api/auth/register', []), 'auth.register', 423);
        app(Flags::class)->set('pause_signups', 'off', null, 'Over');

        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret' => 'secret']);
        app(Flags::class)->set('shield_mode', 'on', null, 'An attack');
        $required = $this->postJson('/api/auth/register', $this->form());
        $this->assertMatchesContract($required, 'auth.register', 403);
        $required->assertJsonPath('code', 'turnstile_required');

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->assertMatchesContract($this->postJson('/api/auth/register', $this->form(['turnstileToken' => 'ok'])), 'auth.register', 202);
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_verification_email_is_sent_again_only_to_an_unverified_account_and_not_too_often(): void
    {
        User::factory()->withPassword()->unverified()->create(['email' => 'ada@example.com']);

        $this->assertMatchesContract($this->postJson('/api/auth/verification', ['email' => 'ada@example.com']), 'auth.verification.resend', 202);
        $this->assertMatchesContract($this->postJson('/api/auth/verification', ['email' => 'ada@example.com']), 'auth.verification.resend', 202);
        $this->assertMatchesContract($this->postJson('/api/auth/verification', ['email' => 'nobody@example.com']), 'auth.verification.resend', 202);

        Mail::assertQueuedCount(1);
    }

    public function test_resends_from_one_network_stop_after_ten_an_hour_and_admin_overrides_count(): void
    {
        foreach (range(1, 11) as $n) {
            User::factory()->withPassword()->unverified()->create(['email' => "singer{$n}@example.com"]);
            // Every answer is the same, sent or not.
            $this->postJson('/api/auth/verification', ['email' => "singer{$n}@example.com"])->assertStatus(202);
        }

        Mail::assertQueuedCount(10);

        // A lower cap set in admin applies to the address's own limit too.
        app(Limits::class)->override('verification-email', 'email_hour', 1, User::factory()->admin()->create(), 'Testing');
        $this->travel(2)->hours();
        $this->postJson('/api/auth/verification', ['email' => 'singer11@example.com'])->assertStatus(202);
        $this->travel(2)->minutes();
        $this->postJson('/api/auth/verification', ['email' => 'singer11@example.com'])->assertStatus(202);
        Mail::assertQueuedCount(11);
    }

    public function test_an_old_link_stops_working_when_a_new_one_is_sent(): void
    {
        $this->postJson('/api/auth/register', $this->form());
        $first = $this->verifyToken('ada@example.com');
        $this->travel(2)->minutes();
        $this->postJson('/api/auth/verification', ['email' => 'ada@example.com']);

        $this->assertMatchesContract($this->postJson('/api/auth/verify', ['token' => $first]), 'auth.verify', 410);
    }
}
