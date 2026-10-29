<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BlockType;
use App\Enums\SecurityEventType;
use App\Models\Block;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Admin\AdminGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

final class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $raw
     */
    private function fakeGoogle(string $id = 'admin-google-id', string $email = 'admin@example.test', array $raw = []): void
    {
        Socialite::fake('google', (new GoogleUser)->map(['id' => $id, 'name' => 'Hillel', 'email' => $email, 'avatar' => null])
            ->setRaw(['email_verified' => true, ...$raw]));
        // Started from the admin's sign-in page, as Hillel does.
        $this->post('/auth/google', ['intended' => '/admin'])->assertRedirect();
    }

    public function test_the_sign_in_page_has_one_button_and_never_eval(): void
    {
        $response = $this->get('/admin/sign-in?intended=/admin/people');

        $response->assertOk()
            ->assertSee(__('admin.sign_in.google'))
            ->assertSee('value="/admin/people"', false)
            ->assertSee('noindex', false);
        $this->assertStringNotContainsString('unsafe-eval', (string) $response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_the_intended_page_is_always_inside_admin(): void
    {
        foreach (['https://evil.test/admin', '//evil.test/admin', '/p/abc', '/admin/sign-in', "/admin/\r\nSet-Cookie:x", '/admin\\..\\evil'] as $intended) {
            $this->get('/admin/sign-in?intended='.urlencode($intended))->assertSee('value="/admin"', false);
        }
    }

    public function test_starting_sign_in_goes_to_google_and_the_honeypot_refuses_bots(): void
    {
        $this->post('/auth/google', ['intended' => '/admin/people'])->assertRedirect();
        $this->assertSame('/admin/people', session('auth.pending.intended'));

        $this->post('/auth/google', ['nickname' => 'bot'])->assertSessionHasErrors('nickname');
    }

    public function test_a_listed_admin_signs_in_freshly_and_goes_to_set_up_the_second_factor(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fakeGoogle();

        $this->withSession(['auth.pending' => ['intended' => '/admin/people', 'link' => false, 'ref' => null]])
            ->get('/auth/google/callback?code=x&state=y')
            ->assertRedirect(route('admin.second-factor.setup'));

        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(session(AdminGate::SESSION_FRESH));
        $this->assertSame('admin@example.test', session(AdminGate::SESSION_EMAIL));
        $this->assertSame('/admin/people', session('url.intended'));
    }

    public function test_the_first_sign_in_of_a_listed_person_makes_their_account_without_a_role(): void
    {
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect();

        $user = User::query()->where('google_id', 'admin-google-id')->firstOrFail();
        $this->assertFalse($user->is_admin, 'The role comes only from app:admin:grant.');
        $this->assertSame('admin@example.test', $user->email);
    }

    public function test_someone_not_on_the_lists_gets_an_account_but_no_way_into_admin(): void
    {
        $this->fakeGoogle('stranger-id', 'stranger@example.test');

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/admin');

        $stranger = User::query()->where('google_id', 'stranger-id')->firstOrFail();
        $this->assertAuthenticatedAs($stranger);
        $this->assertFalse($stranger->is_admin);
        $this->getJson('/api/admin/me')->assertForbidden();
        $this->assertSame(1, SecurityEvent::query()->where('type', SecurityEventType::SignUp)->count());
    }

    public function test_someone_whose_email_is_listed_but_not_their_google_id_is_told_their_id_once(): void
    {
        $this->fakeGoogle('new-admin-id', 'Admin@Example.test');

        $this->post('/auth/google', ['intended' => '/admin']);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirectContains('error=not_admin');
        $this->get('/admin/sign-in?error=not_admin')->assertSee('new-admin-id')->assertSee('app:admin:grant');
        $this->get('/admin/sign-in?error=not_admin')->assertDontSee('new-admin-id');

        // Their account exists now, for app:admin:grant once the ID is on the list.
        $this->assertDatabaseHas('users', ['google_id' => 'new-admin-id', 'is_admin' => false]);

        $this->fakeGoogle('stranger-id', 'stranger@example.test');
        $this->post('/auth/google', ['intended' => '/admin']);
        $this->get('/auth/google/callback?code=x&state=y');
        $this->get('/admin/sign-in?error=not_admin')->assertDontSee('stranger-id');
    }

    public function test_an_unverified_google_email_is_refused(): void
    {
        $this->fakeGoogle(raw: ['email_verified' => false]);

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirectContains('error=unverified');
        $this->assertGuest();
    }

    public function test_an_account_is_matched_by_google_id_never_by_email(): void
    {
        $someoneElse = User::factory()->create(['email' => 'admin@example.test', 'google_id' => 'another-id']);
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect();

        $this->assertNotSame($someoneElse->id, auth()->id());
        $this->assertNull(User::query()->where('google_id', 'admin-google-id')->value('email'), 'The email belongs to someone else, so it is not copied.');
    }

    public function test_a_blocked_email_cannot_sign_in(): void
    {
        User::factory()->admin()->create();
        Block::factory()->create(['type' => BlockType::EmailDomain, 'value' => 'example.test']);
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirectContains('error=blocked');
        $this->assertGuest();
        $this->assertSame(1, SecurityEvent::query()->where('type', SecurityEventType::Blocked)->count());
    }

    public function test_a_suspended_admin_cannot_sign_in(): void
    {
        User::factory()->admin()->suspended()->create();
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirectContains('error=suspended');
        $this->assertGuest();
    }

    public function test_cancelling_at_google_comes_back_with_a_kind_message(): void
    {
        $this->fakeGoogle();
        $this->get('/auth/google/callback?error=access_denied')->assertRedirectContains('error=cancelled');

        $this->get('/admin/sign-in?error=cancelled')->assertSee(__('admin.sign_in.errors.cancelled'));
        $this->get('/admin/sign-in?error=<script>')->assertDontSee('<script>', false);
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/sign-out')->assertRedirect(route('admin.sign-in'));
        $this->assertGuest();
    }

    public function test_someone_signed_in_without_admin_rights_can_still_sign_out(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/admin/sign-out')->assertRedirect(route('admin.sign-in'));
        $this->assertGuest();
    }
}
