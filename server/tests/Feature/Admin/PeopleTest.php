<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SecurityEventType;
use App\Mail\Account\RecoveredBySupport;
use App\Mail\Account\ResetPassword;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Trust\SecurityLog;
use App\Support\Trust\TrustLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class PeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_finds_people_by_email_handle_or_name(): void
    {
        $this->actingAsAdmin();
        $ada = User::factory()->create(['email' => 'ada@example.com', 'handle' => 'ada_sings', 'name' => 'Ada Lovelace']);
        User::factory()->create(['email' => 'bo@example.net', 'apple_id' => 'a-1', 'google_id' => null]);
        app(SecurityLog::class)->record(SecurityEventType::SignIn, $ada, '203.0.113.9', ['method' => 'google']);

        $all = $this->getJson('/api/admin/people');
        $this->assertMatchesContract($all, 'admin.people.list', 200);
        $this->assertCount(3, (array) $all->json('data'));

        $found = $this->getJson('/api/admin/people?q=ada_s');
        $found->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ada->sqid())->assertJsonPath('data.0.trustLevel', TrustLevel::VERIFIED);
        $this->assertNotNull($found->json('data.0.lastSignInAt'));
        $this->getJson('/api/admin/people?q=%25')->assertJsonCount(0, 'data');

        $shown = $this->getJson('/api/admin/people/'.$ada->sqid());
        $this->assertMatchesContract($shown, 'admin.people.show', 200);
        $this->assertMatchesContract($this->getJson('/api/admin/people/nope'), 'admin.people.show', 404);
    }

    public function test_people_are_the_admins_alone(): void
    {
        $this->actingAsEditor();

        $this->assertMatchesContract($this->getJson('/api/admin/people'), 'admin.people.list', 403);
    }

    public function test_suspending_needs_sudo_a_reason_and_ends_every_session(): void
    {
        $admin = $this->actingAsAdmin(secondFactorPassed: false);
        $person = User::factory()->create();
        $this->postJson('/api/admin/people/'.$person->sqid().'/suspend', ['reason' => 'Spam'])->assertForbidden()->assertJsonPath('code', 'second_factor_required');

        $this->actingAsAdmin($admin);
        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$person->sqid().'/suspend', []), 'admin.people.suspend', 422);

        $token = $person->remember_token;
        $suspended = $this->postJson('/api/admin/people/'.$person->sqid().'/suspend', ['reason' => 'Spam links in every bio']);
        $this->assertMatchesContract($suspended, 'admin.people.suspend', 200);
        $this->assertNotNull($suspended->json('data.suspendedAt'));
        $this->assertNotSame($token, $person->refresh()->remember_token);
        $this->assertSame('Spam links in every bio', AuditLog::query()->where('action', 'person.suspended')->value('reason'));

        $back = $this->postJson('/api/admin/people/'.$person->sqid().'/unsuspend', ['reason' => 'Appeal upheld']);
        $this->assertMatchesContract($back, 'admin.people.unsuspend', 200);
        $back->assertJsonPath('data.suspendedAt', null);
    }

    public function test_staff_accounts_are_changed_only_from_the_console(): void
    {
        $this->actingAsAdmin();
        $editor = User::factory()->editor()->create();

        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$editor->sqid().'/suspend', ['reason' => 'x']), 'admin.people.suspend', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$editor->sqid().'/recover', ['email' => 'new@example.com', 'reason' => 'x']), 'admin.people.recover', 409);
        $this->assertFalse($editor->refresh()->isSuspended());
    }

    public function test_the_trust_level_is_set_by_hand_and_given_back(): void
    {
        $this->actingAsAdmin();
        $person = User::factory()->create();

        $this->assertMatchesContract($this->putJson('/api/admin/people/'.$person->sqid().'/trust', ['level' => 5, 'note' => 'x']), 'admin.people.trust.update', 422);

        $set = $this->putJson('/api/admin/people/'.$person->sqid().'/trust', ['level' => 0, 'note' => 'Spam reports upheld twice']);
        $this->assertMatchesContract($set, 'admin.people.trust.update', 200);
        $set->assertJsonPath('data.trustLevel', 0)->assertJsonPath('data.trustOverride', 0);

        $this->putJson('/api/admin/people/'.$person->sqid().'/trust', ['level' => null, 'note' => 'Cleared'])
            ->assertJsonPath('data.trustLevel', TrustLevel::VERIFIED)->assertJsonPath('data.trustNote', null);
    }

    public function test_recovery_gives_a_lost_google_account_a_new_address_and_a_password_link(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $person = User::factory()->create(['email' => 'lost@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$person->sqid().'/recover', ['email' => 'nope', 'reason' => 'x']), 'admin.people.recover', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/people/'.$person->sqid().'/recover', ['email' => 'taken@example.com', 'reason' => 'x']), 'admin.people.recover', 409);

        $token = $person->remember_token;
        $recovered = $this->postJson('/api/admin/people/'.$person->sqid().'/recover', ['email' => 'Found@Example.com', 'reason' => 'Matched the last receipt and two project titles']);
        $this->assertMatchesContract($recovered, 'admin.people.recover', 200);
        $recovered->assertJsonPath('data.email', 'found@example.com')->assertJsonPath('data.emailVerified', true);

        Mail::assertQueued(ResetPassword::class, fn (ResetPassword $mail): bool => $mail->hasTo('found@example.com'));
        Mail::assertQueued(RecoveredBySupport::class, fn (RecoveredBySupport $mail): bool => $mail->hasTo('lost@example.com'));
        $this->assertNotSame($token, $person->refresh()->remember_token);

        $audit = AuditLog::query()->where('action', 'person.recovered')->firstOrFail();
        $this->assertStringNotContainsString('found@example.com', (string) json_encode($audit->after));
    }

    public function test_the_person_page_shows_their_plan_and_age_band_never_their_birth_date_and_the_list_filters(): void
    {
        $admin = $this->actingAsAdmin();
        $adult = User::factory()->create(['name' => 'Ana', 'birth_month' => 5, 'birth_year' => 1990, 'adult_confirmed_at' => now(), 'plan' => 'plus', 'store_tester_at' => now()]);
        $young = User::factory()->create(['name' => 'Ben', 'birth_month' => 5, 'birth_year' => (int) now()->year - 15, 'adult_confirmed_at' => null]);
        $unknown = User::factory()->create(['name' => 'Cal', 'birth_month' => null, 'birth_year' => null, 'adult_confirmed_at' => null]);

        $shown = $this->getJson('/api/admin/people/'.$adult->sqid());
        $this->assertMatchesContract($shown, 'admin.people.show', 200);
        $shown->assertJsonPath('data.adult', true)->assertJsonPath('data.plan.plus', true)->assertJsonPath('data.plan.source', 'demo')->assertJsonPath('data.counts.groups', 0);
        $this->assertStringNotContainsString('1990', (string) $shown->getContent());
        // Usage as counts only: the person page has the fuller set, a list row the few.
        $shown->assertJsonPath('data.stats.songs', 0)->assertJsonPath('data.stats.level', 1)->assertJsonPath('data.stats.lastActivity', null)->assertJsonPath('data.stats.practiceMinutes.total', 0);
        $this->getJson('/api/admin/people/'.$young->sqid())->assertJsonPath('data.adult', false)->assertJsonPath('data.plan.plus', false);
        $this->getJson('/api/admin/people/'.$unknown->sqid())->assertJsonPath('data.adult', null);

        $ids = fn (string $query): array => array_column((array) $this->getJson('/api/admin/people?'.$query)->assertOk()->json('data'), 'id');
        $this->assertSame([$adult->sqid()], $ids('plan=plus'));
        $this->assertSame([$young->sqid()], $ids('adult=0'));
        $this->assertSame([$adult->sqid()], $ids('role=tester'));
        $this->assertNotContains($adult->sqid(), $ids('plan=free'));
        $listed = $this->getJson('/api/admin/people?sort=active');
        $this->assertMatchesContract($listed, 'admin.people.list', 200);
        $this->assertSame(['songs', 'lessonsDone', 'streakWeeks', 'lastActivity'], array_keys((array) $listed->json('data.0.stats')));

        // An account's own audit entries: about them, or done by them.
        $this->putJson('/api/admin/people/'.$young->sqid().'/trust', ['level' => 2, 'note' => 'Known to us.'])->assertOk();
        $entries = (array) $this->getJson('/api/admin/audit?user='.$young->sqid())->assertOk()->json('data');
        $this->assertNotEmpty($entries);
        $this->assertSame([], (array) $this->getJson('/api/admin/audit?user='.$unknown->sqid())->json('data'));
        $this->assertNotNull($admin);
    }
}
