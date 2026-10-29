<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SecurityEventType;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\Admin\AdminSignedIn;
use App\Notifications\Admin\SecondFactorAdded;
use App\Notifications\Admin\SecondFactorLocked;
use App\Support\Admin\SecondFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class SecondFactorTest extends TestCase
{
    use RefreshDatabase;

    private function signedInWithoutSecondFactor(?User $user = null): User
    {
        $user ??= User::factory()->admin()->create();
        $this->actingAs($user)->withSession($this->adminSession($user, secondFactorPassed: false));

        return $user;
    }

    public function test_setup_is_forced_and_shows_a_qr_code_and_passkey_button(): void
    {
        $this->signedInWithoutSecondFactor();

        $this->get('/admin/second-factor')->assertRedirect(route('admin.second-factor.setup'));

        $response = $this->get('/admin/second-factor/setup');
        $response->assertOk()
            ->assertSee(__('admin.second_factor.passkey_add'))
            ->assertSee('<svg', false)
            ->assertSee('/assets/passkeys.js', false);
        $this->assertStringNotContainsString('unsafe-eval', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_turning_on_the_authenticator_app_shows_recovery_codes_once_and_passes_the_check(): void
    {
        Notification::fake();
        $admin = $this->signedInWithoutSecondFactor();

        $this->get('/admin/second-factor/setup')->assertOk();
        $secret = (string) session('admin.totp_pending');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post('/admin/second-factor/totp', ['code' => $code])->assertRedirect(route('admin.second-factor.setup'));

        $codes = session('admin.recovery_codes');
        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);
        $this->get('/admin/second-factor/setup')->assertSee($codes[0]);

        $this->withSession(['url.intended' => '/admin/people'])->post('/admin/second-factor/finish')->assertRedirect('/admin/people');
        $this->get('/admin/second-factor/setup')->assertRedirect(route('admin.second-factor.challenge'));

        $this->assertTrue(app(SecondFactor::class)->passedThisSession(session()->driver(), $admin->refresh()));
        $this->assertNotContains($codes[0], (array) $admin->secondFactor?->recovery_codes, 'Only hashes are stored.');
        Notification::assertSentTo($admin, AdminSignedIn::class);
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.totp_enabled')->count());
        $this->assertSame(1, SecurityEvent::query()->where('type', SecurityEventType::AdminSignIn)->count());
    }

    public function test_a_wrong_setup_code_is_refused(): void
    {
        $this->signedInWithoutSecondFactor();
        $this->get('/admin/second-factor/setup');

        $this->post('/admin/second-factor/totp', ['code' => '000000'])->assertSessionHasErrors('code');
    }

    public function test_the_challenge_takes_a_current_code_once(): void
    {
        $admin = User::factory()->admin()->create();
        $secret = app(SecondFactor::class)->newTotpSecret();
        app(SecondFactor::class)->enableTotp($admin, $secret);
        $this->signedInWithoutSecondFactor($admin);

        $this->get('/admin/second-factor')->assertOk()->assertSee(__('admin.second_factor.code_or_recovery_label'));

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->withSession(['url.intended' => '/admin/people'])
            ->post('/admin/second-factor/challenge/code', ['code' => $code])
            ->assertRedirect('/admin/people');

        // The same code can't be used again (replay).
        $this->assertSame('invalid', app(SecondFactor::class)->attempt($admin->refresh(), $code));
    }

    public function test_the_sudo_check_returns_to_the_admin_page_it_was_asked_from(): void
    {
        $admin = User::factory()->admin()->create();
        $secret = app(SecondFactor::class)->newTotpSecret();
        app(SecondFactor::class)->enableTotp($admin, $secret);
        $this->actingAs($admin)->withSession($this->adminSession($admin));

        $this->get('/admin/second-factor?sudo=1&intended='.urlencode('/admin/safety?tab=limits'))->assertOk()->assertSee(__('admin.second_factor.sudo_intro'));
        $this->post('/admin/second-factor/challenge/code', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertRedirect('/admin/safety?tab=limits');

        $this->get('/admin/second-factor?sudo=1&intended=https://evil.test/')->assertOk();
        $this->assertSame('/admin', session('url.intended'));

        session()->forget('url.intended');
        $this->get('/admin/second-factor?sudo=1', ['Referer' => url('/admin/blocklist')])->assertOk();
        $this->assertSame('/admin/blocklist', session('url.intended'));
    }

    public function test_a_recovery_code_works_once(): void
    {
        $admin = User::factory()->admin()->create();
        $factor = app(SecondFactor::class)->enableTotp($admin, app(SecondFactor::class)->newTotpSecret());
        $codes = app(SecondFactor::class)->newRecoveryCodes($factor);

        $this->assertSame('ok', app(SecondFactor::class)->attempt($admin->refresh(), $codes[3]));
        $this->assertSame('invalid', app(SecondFactor::class)->attempt($admin->refresh(), $codes[3]));
        $this->assertCount(7, (array) $admin->refresh()->secondFactor?->recovery_codes);
    }

    public function test_too_many_wrong_codes_lock_admin_and_tell_every_admin(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        app(SecondFactor::class)->enableTotp($admin, app(SecondFactor::class)->newTotpSecret());
        $this->signedInWithoutSecondFactor($admin);

        foreach (range(1, 5) as $attempt) {
            $this->post('/admin/second-factor/challenge/code', ['code' => '123456'])->assertSessionHasErrors('code');
        }

        $this->assertTrue($admin->refresh()->secondFactor?->isLocked());
        Notification::assertSentTo($admin, SecondFactorLocked::class);
        $this->assertSame(1, SecurityEvent::query()->where('type', SecurityEventType::SecondFactorLocked)->count());
        $this->assertSame('locked', app(SecondFactor::class)->attempt($admin->refresh(), '123456'));
    }

    public function test_once_locked_no_attempt_is_even_checked_until_the_lock_runs_out(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $secret = app(SecondFactor::class)->newTotpSecret();
        app(SecondFactor::class)->enableTotp($admin, $secret);
        $right = app(Google2FA::class)->getCurrentOtp($secret);

        foreach (range(1, 5) as $i) {
            app(SecondFactor::class)->attempt($admin->refresh(), '000000');
        }

        // Even the right code isn't checked while locked (as parallel requests would try).
        $this->assertSame('locked', app(SecondFactor::class)->attempt($admin->refresh(), $right));
        $this->assertSame(5, $admin->refresh()->secondFactor?->failed_attempts);

        // When the lock runs out: one guess, and a wrong one locks again, for longer, with a new alert.
        $this->travel(5)->minutes();
        $this->assertSame('locked', app(SecondFactor::class)->attempt($admin->refresh(), '000000'));
        $this->assertTrue($admin->refresh()->secondFactor?->locked_until?->isAfter(now()->addMinutes(7)));
        Notification::assertSentToTimes($admin, SecondFactorLocked::class, 2);

        $this->travel(9)->minutes();
        $this->assertSame('ok', app(SecondFactor::class)->attempt($admin->refresh(), app(Google2FA::class)->getCurrentOtp($secret)));
        $this->assertSame(0, $admin->refresh()->secondFactor?->failed_attempts);
    }

    public function test_the_count_of_wrong_codes_starts_again_after_a_quiet_day(): void
    {
        $admin = User::factory()->admin()->create();
        app(SecondFactor::class)->enableTotp($admin, app(SecondFactor::class)->newTotpSecret());

        foreach (range(1, 4) as $i) {
            app(SecondFactor::class)->attempt($admin->refresh(), '000000');
        }

        $this->travel(25)->hours();
        $this->assertSame('invalid', app(SecondFactor::class)->attempt($admin->refresh(), '000000'));
        $this->assertSame(1, $admin->refresh()->secondFactor?->failed_attempts);
    }

    public function test_a_failed_passkey_is_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        app(SecondFactor::class)->enableTotp($admin, app(SecondFactor::class)->newTotpSecret());
        $this->signedInWithoutSecondFactor($admin);
        $this->withSession(['admin.passkey_verification' => '{}']);

        $this->postJson('/admin/second-factor/challenge/passkey', ['credential' => ['id' => 'nope']])->assertStatus(422);

        $this->assertSame(['method' => 'passkey'], SecurityEvent::query()->where('type', SecurityEventType::SecondFactorFailed)->firstOrFail()->context);
    }

    public function test_an_enrolled_admin_in_sudo_can_replace_the_authenticator_app_and_every_admin_hears(): void
    {
        Notification::fake();
        $admin = $this->actingAsAdmin();
        $oldSecret = (string) $admin->secondFactor?->totp_secret;

        $this->withSession(['admin.totp_pending' => $new = app(SecondFactor::class)->newTotpSecret()])
            ->post('/admin/second-factor/totp', ['code' => app(Google2FA::class)->getCurrentOtp($new)])
            ->assertRedirect(route('admin.second-factor.setup'));

        $this->assertNotSame($oldSecret, $admin->refresh()->secondFactor?->totp_secret);
        Notification::assertSentTo($admin, SecondFactorAdded::class);
    }

    public function test_a_second_factor_pass_never_carries_over_to_another_account(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->editor()->create();
        $session = $this->adminSession($admin);

        $this->assertFalse(app(SecondFactor::class)->passedThisSession(tap(app('session')->driver(), fn ($s) => $s->put($session)), $other));
    }

    public function test_adding_another_factor_needs_a_fresh_check(): void
    {
        $this->actingAsAdmin();
        $this->travel(31)->minutes();

        $this->getJson('/admin/second-factor/passkey/options')->assertStatus(423)->assertJsonStructure(['confirm']);
    }

    public function test_passkey_options_are_offered_to_someone_setting_up(): void
    {
        $this->signedInWithoutSecondFactor();

        $this->getJson('/admin/second-factor/passkey/options')->assertOk()->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
        $this->assertIsString(session('admin.passkey_registration'));
    }

    public function test_a_bad_passkey_is_refused_kindly(): void
    {
        $this->signedInWithoutSecondFactor();
        $this->getJson('/admin/second-factor/passkey/options')->assertOk();

        $this->postJson('/admin/second-factor/passkey', ['credential' => ['id' => 'nope']])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin.second_factor.passkey_not_saved'));
    }

    public function test_the_second_factor_pages_need_an_admin_or_editor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession($this->adminSession($user, secondFactorPassed: false));

        $this->get('/admin/second-factor/setup')->assertForbidden()->assertSee(__('admin.not_admin.title'));
    }

    public function test_signed_out_people_are_sent_to_sign_in(): void
    {
        $this->get('/admin/second-factor')->assertRedirect(route('admin.sign-in', ['intended' => '/admin/second-factor']));
    }
}
