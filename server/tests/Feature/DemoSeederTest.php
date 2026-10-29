<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\EsConnection;
use App\Models\Group;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\Project;
use App\Models\Take;
use App\Models\Template;
use App\Models\User;
use App\Services\Coaching\CoachingGateway;
use App\Services\EventSchedule\EventSchedule;
use App\Services\EventSchedule\FakeEventSchedule;
use App\Services\InvoiceNinja\FakeInvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinja;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCoachingGateway;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_admin_and_editor_are_seeded_and_it_runs_again_safely(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(8, User::query()->count());
        $this->assertSame(1, Project::query()->where('uuid', DemoSeeder::DEMO_PROJECT)->count());
        $this->assertSame(2, Take::query()->count());
        $this->postJson('/api/auth/sign-in', ['email' => 'ada@example.com', 'password' => 'demo-password-for-the-flows'])->assertOk();
        $this->postJson('/api/auth/sign-out');
        $this->postJson('/api/auth/verify', ['token' => 'demo-verify-link-for-the-flow-scripts-0001'])->assertOk()->assertJsonPath('data.emailVerified', true);
        $this->assertGreaterThan(0, Template::query()->count());
        $this->getJson('/api/beta/invites/demo-singer-link-for-the-flow-scripts-0001')->assertOk()->assertJsonPath('data.status', 'open');
        $this->postJson('/api/auth/sign-in', ['email' => 'sarah@example.com', 'password' => 'demo-password-for-the-flows'])->assertOk()->assertJsonPath('data.betaSinger', true);
        $this->postJson('/api/auth/sign-out');
        $this->assertTrue(User::query()->where('google_id', 'dev-admin')->firstOrFail()->is_admin);
        $this->assertTrue(User::query()->where('google_id', 'dev-editor')->firstOrFail()->is_editor);
    }

    public function test_with_the_fake_event_schedule_ada_has_a_booked_lesson_and_pias_choir_its_rehearsals(): void
    {
        $this->app->instance(EventSchedule::class, new FakeEventSchedule);
        $this->app->instance(CoachingGateway::class, new FakeCoachingGateway);
        $this->app->instance(InvoiceNinja::class, new FakeInvoiceNinja);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, EsConnection::query()->count());
        $this->assertSame(2, InvoiceNinjaConnection::query()->count());
        $this->assertSame(4, InvoiceNinjaRecord::query()->where('status', InvoiceNinjaRecord::RECORDED)->count());
        $lesson = Engagement::query()->where('kind', Engagement::LESSON)->sole();
        $this->postJson('/api/auth/sign-in', ['email' => 'ada@example.com', 'password' => 'demo-password-for-the-flows'])->assertOk();
        $this->getJson('/api/engagements/'.$lesson->sqid())->assertOk()->assertJsonPath('data.engagement.lesson.status', 'confirmed');
        $group = Group::query()->sole();
        $this->getJson('/api/groups/'.$group->sqid().'/rehearsals')->assertOk()->assertJsonPath('data.events.0.songs.0.templateId', 'amazing-grace')->assertJsonPath('data.events.0.songs.0.partKey', 'alto');

        // Playing ES's side: the coach cancels, and Ada is refunded.
        $this->artisan('app:event-schedule:fake', ['engagement' => $lesson->sqid(), 'change' => 'cancelled'])->assertSuccessful();
        $this->assertSame(Engagement::REFUNDED, $lesson->refresh()->status);
    }
}
