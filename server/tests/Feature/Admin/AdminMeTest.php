<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\AdminGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_who_they_are_with_their_role_and_sudo_window(): void
    {
        $this->freezeSecond();
        $admin = $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/me');

        $this->assertMatchesContract($response, 'admin.me', 200);
        $response->assertJson(['data' => [
            'id' => $admin->sqid(),
            'name' => $admin->name,
            'email' => 'admin@example.test',
            'role' => 'admin',
            'sudoUntil' => now()->addMinutes(30)->utc()->toIso8601ZuluString(),
        ]]);
        $this->assertStringNotContainsString((string) $admin->id.',', (string) $response->getContent());
    }

    public function test_an_editor_is_an_editor(): void
    {
        $this->actingAsEditor();

        $response = $this->getJson('/api/admin/me');

        $this->assertMatchesContract($response, 'admin.me', 200);
        $response->assertJsonPath('data.role', 'editor');
    }

    public function test_the_sudo_window_closes(): void
    {
        $this->actingAsAdmin();
        $this->travel(31)->minutes();

        $response = $this->getJson('/api/admin/me');

        $this->assertMatchesContract($response, 'admin.me', 200);
        $response->assertJsonPath('data.sudoUntil', null);
    }

    public function test_signed_out_is_unauthenticated(): void
    {
        $this->assertMatchesContract($this->getJson('/api/admin/me'), 'admin.me', 401);
    }

    public function test_someone_without_a_role_is_not_an_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession($this->adminSession($user));

        $response = $this->getJson('/api/admin/me');

        $this->assertMatchesContract($response, 'admin.me', 403);
        $response->assertJsonPath('code', 'not_admin');
    }

    public function test_a_flag_in_the_database_alone_never_makes_an_admin(): void
    {
        // is_admin is set, but the Google ID isn't on ADMIN_GOOGLE_IDS.
        $user = User::factory()->create(['is_admin' => true, 'email' => 'admin@example.test']);
        $this->actingAs($user)->withSession($this->adminSession($user));

        $this->assertMatchesContract($this->getJson('/api/admin/me'), 'admin.me', 403);
    }

    public function test_a_remember_me_restore_never_grants_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $session = $this->adminSession($admin);
        unset($session[AdminGate::SESSION_FRESH], $session[AdminGate::SESSION_EMAIL]);
        $this->actingAs($admin)->withSession($session);

        $this->assertMatchesContract($this->getJson('/api/admin/me'), 'admin.me', 403);
    }

    public function test_the_second_factor_is_required(): void
    {
        $this->actingAsAdmin(secondFactorPassed: false);

        $response = $this->getJson('/api/admin/me');

        $this->assertMatchesContract($response, 'admin.me', 403);
        $response->assertJsonPath('code', 'second_factor_required');
    }

    public function test_an_idle_admin_session_ends(): void
    {
        $this->actingAsAdmin();
        $this->travel(241)->minutes();

        $this->assertMatchesContract($this->getJson('/api/admin/me'), 'admin.me', 401);
    }

    public function test_an_admin_session_is_tied_to_its_browser(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/me', ['User-Agent' => 'Another browser']);

        $this->assertMatchesContract($response, 'admin.me', 401);
    }

    public function test_a_suspended_admin_is_not_an_admin(): void
    {
        $this->actingAsAdmin(User::factory()->admin()->suspended()->create());

        $this->assertMatchesContract($this->getJson('/api/admin/me'), 'admin.me', 403);
    }

    public function test_the_answer_is_never_cached(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/me')->assertHeader('Cache-Control', 'no-store, private');
    }
}
