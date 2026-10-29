<?php

declare(strict_types=1);

namespace Tests\Feature\Coaching;

use App\Models\CoachApplication;
use App\Models\CoachProfile;
use App\Models\ExercisePack;
use App\Models\User;
use App\Support\Flags;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Coaching;
use Tests\TestCase;

final class BecomingACoachTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Coaches sign up at launch (docs/launch-plan.md); the directory stays its own switch.
        app(Flags::class)->set('coach_signup', 'on', null, 'Coach sign-up');
    }

    public function test_applications_wait_for_coach_sign_up_to_open(): void
    {
        app(Flags::class)->set('coach_signup', 'off', null, 'Not yet');
        $this->as(User::factory()->create(['handle' => 'grace_sings']));
        $this->getJson('/api/auth/config')->assertJsonPath('data.coachSignup', false);

        $closed = $this->postJson('/api/coach/application', $this->application());
        $this->assertMatchesContract($closed, 'coach.application.create', 409);
        $closed->assertJsonPath('code', 'coach_signup_closed');
    }

    /**
     * @return array<string, mixed>
     */
    private function application(array $with = []): array
    {
        return [
            'experience' => 'Twelve years teaching community choirs.',
            'training' => 'Vocal pedagogy.',
            'voiceTypes' => ['alto'],
            'styles' => ['choral'],
            'languages' => ['en'],
            'timezone' => 'Europe/London',
            'introUrl' => null,
            'sampleReview' => ['comments' => [['atSeconds' => 3.5, 'text' => 'Nice start.']], 'summary' => ['working' => 'Warm tone.', 'toWorkOn' => ['Breath'], 'practise' => 'Slow scales.']],
            'agreementVersion' => 1,
            'adult' => true,
            ...$with,
        ];
    }

    public function test_someone_applies_with_a_sample_review_and_hillel_approves_them_with_plus_free(): void
    {
        $user = $this->as(User::factory()->create(['handle' => null]));
        $none = $this->getJson('/api/coach/application');
        $this->assertMatchesContract($none, 'coach.application.show', 200);
        $none->assertJsonPath('data', null);

        $this->assertMatchesContract($this->postJson('/api/coach/application', $this->application()), 'coach.application.create', 409);
        $user->forceFill(['handle' => 'grace_sings'])->save();
        $this->as($user);
        $this->assertMatchesContract($this->postJson('/api/coach/application', $this->application(['adult' => false, 'experience' => 'short'])), 'coach.application.create', 422);

        $applied = $this->postJson('/api/coach/application', $this->application());
        $this->assertMatchesContract($applied, 'coach.application.create', 201);
        $applied->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.sampleReview.summary.toWorkOn', ['Breath']);
        $this->postJson('/api/coach/application', $this->application())->assertStatus(409)->assertJsonPath('code', 'application_open');
        $this->assertMatchesContract($this->getJson('/api/coach/profile'), 'coach.profile.show', 404);

        $this->actingAsAdmin();
        $queue = $this->getJson('/api/admin/coaching/applications');
        $this->assertMatchesContract($queue, 'admin.coaching.applications.list', 200);
        $id = (string) $queue->json('data.0.application.id');
        $decided = $this->postJson('/api/admin/coaching/applications/'.$id, ['decision' => 'approve', 'note' => 'Welcome!']);
        $this->assertMatchesContract($decided, 'admin.coaching.applications.decide', 200);
        $this->assertMatchesContract($this->postJson('/api/admin/coaching/applications/'.$id, ['decision' => 'approve']), 'admin.coaching.applications.decide', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/coaching/applications/nope', ['decision' => 'approve']), 'admin.coaching.applications.decide', 404);

        $coach = $user->fresh() ?? $user;
        $this->assertTrue(app(Plans::class)->isPlus($coach));
        $this->as($coach);
        $profile = $this->getJson('/api/coach/profile');
        $this->assertMatchesContract($profile, 'coach.profile.show', 200);
        $profile->assertJsonPath('data.canSell', false)->assertJsonPath('data.handle', 'grace_sings');
    }

    public function test_a_declined_applicant_can_apply_again_after_30_days(): void
    {
        $user = $this->as(User::factory()->create(['handle' => 'kim_sings']));
        CoachApplication::factory()->create(['user_id' => $user->id, 'status' => CoachApplication::DECLINED, 'decided_at' => now()->subDays(10)]);
        $this->postJson('/api/coach/application', $this->application())->assertStatus(409);

        $this->travel(21)->days();
        $this->postJson('/api/coach/application', $this->application())->assertCreated();
    }

    public function test_a_coach_sets_up_their_profile_stripe_offerings_and_packs(): void
    {
        $stripe = $this->fakeConnect();
        [$coach] = $this->coach();
        CoachProfile::query()->where('user_id', $coach->id)->update(['stripe_account_id' => null, 'charges_enabled' => false]);
        $this->as($coach);

        $updated = $this->patchJson('/api/coach/profile', ['bio' => 'Choir director.', 'cancellationHours' => 48]);
        $this->assertMatchesContract($updated, 'coach.profile.update', 200);
        $this->assertMatchesContract($this->patchJson('/api/coach/profile', ['bio' => 'See mysite.com']), 'coach.profile.update', 422);

        $stripe->ready = false;
        $this->assertMatchesContract($this->postJson('/api/coach/connect', ['country' => 'GB']), 'coach.connect.create', 423);
        $stripe->ready = true;
        $link = $this->postJson('/api/coach/connect', ['country' => 'GB']);
        $this->assertMatchesContract($link, 'coach.connect.create', 200);
        $status = $this->getJson('/api/coach/connect');
        $this->assertMatchesContract($status, 'coach.connect.show', 200);
        $status->assertJsonPath('data.chargesEnabled', true);
        $this->getJson('/api/coach/profile')->assertJsonPath('data.canSell', true);

        $made = $this->postJson('/api/coach/offerings', ['kind' => 'package', 'title' => 'Monthly coaching', 'description' => 'Four reviews a month.', 'priceCents' => 6000, 'currency' => 'gbp', 'turnaroundDays' => 5, 'reviewsPerMonth' => 4]);
        $this->assertMatchesContract($made, 'coach.offerings.create', 201);
        $this->assertMatchesContract($this->postJson('/api/coach/offerings', ['kind' => 'review', 'title' => 'x', 'description' => 'y', 'priceCents' => 100, 'currency' => 'jpy', 'turnaroundDays' => 9]), 'coach.offerings.create', 422);
        $changed = $this->patchJson('/api/coach/offerings/'.$made->json('data.id'), ['priceCents' => 7000, 'archived' => true]);
        $this->assertMatchesContract($changed, 'coach.offerings.update', 200);
        $changed->assertJsonPath('data.archived', true);
        $this->assertMatchesContract($this->patchJson('/api/coach/offerings/nope', ['priceCents' => 7000]), 'coach.offerings.update', 404);
        $this->assertMatchesContract($this->getJson('/api/coach/offerings'), 'coach.offerings.list', 200);

        $pack = (string) Str::uuid();
        $saved = $this->putJson('/api/coach/packs/'.$pack, ['title' => 'Breath', 'description' => 'Five exercises.', 'priceCents' => 0, 'currency' => 'gbp', 'exercises' => [['id' => 'breath-4-4', 'title' => 'Four in, four out']]]);
        $this->assertMatchesContract($saved, 'coach.packs.save', 200);
        $this->assertMatchesContract($this->putJson('/api/coach/packs/'.$pack, ['title' => 'Breath', 'description' => 'x', 'priceCents' => 100, 'currency' => 'gbp', 'exercises' => []]), 'coach.packs.save', 422);
        $this->assertMatchesContract($this->postJson('/api/coach/packs/'.$pack.'/submit'), 'coach.packs.submit', 200);
        $this->assertMatchesContract($this->getJson('/api/coach/packs'), 'coach.packs.list', 200);

        $this->actingAsAdmin();
        $waiting = $this->getJson('/api/admin/coaching/packs');
        $this->assertMatchesContract($waiting, 'admin.coaching.packs.list', 200);
        $waiting->assertJsonPath('data.0.id', $pack);
        $this->assertMatchesContract($this->postJson('/api/admin/coaching/packs/'.$pack, ['decision' => 'approve']), 'admin.coaching.packs.decide', 200);
        $this->assertMatchesContract($this->postJson('/api/admin/coaching/packs/'.Str::uuid(), ['decision' => 'approve']), 'admin.coaching.packs.decide', 404);
        $this->assertSame(ExercisePack::APPROVED, ExercisePack::query()->where('uuid', $pack)->value('status'));

        // The public profile, by its link, for anyone.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $public = $this->getJson('/api/coaches/'.$coach->handle);
        $this->assertMatchesContract($public, 'coaches.show', 200);
        $public->assertJsonPath('data.newCoach', true)->assertJsonCount(1, 'data.offerings')->assertJsonCount(1, 'data.packs');
        $this->assertMatchesContract($this->getJson('/api/coaches/nobody'), 'coaches.show', 404);

        // A change after approval waits for its review: buyers see, and pay, what Hillel approved.
        $this->as($coach);
        $this->putJson('/api/coach/packs/'.$pack, ['title' => 'Breath', 'description' => 'Write to me at coach@example.com.', 'priceCents' => 9900, 'currency' => 'gbp', 'exercises' => [['id' => 'breath-4-4', 'title' => 'Four in, four out']]])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->getJson('/api/coaches/'.$coach->handle)->assertJsonPath('data.packs.0.description', 'Five exercises.')->assertJsonPath('data.packs.0.priceCents', 0);
    }

    public function test_a_non_coach_has_no_coach_tools(): void
    {
        $this->as(User::factory()->create());

        $this->assertMatchesContract($this->patchJson('/api/coach/profile', ['bio' => 'Me']), 'coach.profile.update', 404);
        $this->assertMatchesContract($this->postJson('/api/coach/connect'), 'coach.connect.create', 404);
        $this->assertMatchesContract($this->getJson('/api/coach/connect'), 'coach.connect.show', 404);
        $this->assertMatchesContract($this->postJson('/api/coach/offerings', ['kind' => 'review', 'title' => 'x', 'description' => 'y', 'priceCents' => 1000, 'currency' => 'gbp', 'turnaroundDays' => 3]), 'coach.offerings.create', 404);
        $this->assertMatchesContract($this->putJson('/api/coach/packs/'.Str::uuid(), ['title' => 'x', 'description' => 'y', 'priceCents' => 0, 'currency' => 'gbp', 'exercises' => [['id' => 'a', 'title' => 'b']]]), 'coach.packs.save', 404);
        $this->assertMatchesContract($this->postJson('/api/coach/packs/'.Str::uuid().'/submit'), 'coach.packs.submit', 404);
    }

    public function test_hillel_pauses_or_suspends_a_coach(): void
    {
        [$coach] = $this->coach();
        $this->actingAsAdmin();

        $paused = $this->putJson('/api/admin/coaching/coaches/'.$coach->sqid(), ['status' => 'paused', 'note' => 'On holiday']);
        $this->assertMatchesContract($paused, 'admin.coaching.coaches.update', 200);
        $this->assertNull($coach->fresh()?->coach_active_at);
        $this->assertMatchesContract($this->putJson('/api/admin/coaching/coaches/nope', ['status' => 'paused', 'note' => 'x']), 'admin.coaching.coaches.update', 404);
        $this->getJson('/api/coaches/'.$coach->handle)->assertNotFound();
    }
}
