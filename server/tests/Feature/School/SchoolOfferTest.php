<?php

declare(strict_types=1);

namespace Tests\Feature\School;

use App\Mail\Account\ClassCodesReady;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\SchoolRedemption;
use App\Models\User;
use App\Support\Flags;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The school offer (docs/school-offer-plan.md): a teacher's class codes, a student's free year of
 * Plus from one, and Admin's view. Teacher-led, with no groups: the teacher never sees who.
 */
final class SchoolOfferTest extends TestCase
{
    use RefreshDatabase;

    private function open(): void
    {
        app(Flags::class)->set('schools', 'on', null, 'Open');
    }

    private function teacher(array $with = []): User
    {
        return User::factory()->create(['adult_confirmed_at' => now(), ...$with]);
    }

    public function test_it_is_closed_until_switched_on(): void
    {
        $this->actingAs($this->teacher());
        $this->assertMatchesContract($this->getJson('/api/school/classes'), 'school.classes.list', 404);
        $this->postJson('/api/school/redeem', ['code' => 'KQ7M-3XPA'])->assertNotFound();
        $this->getJson('/api/auth/config')->assertJsonPath('data.schools', false);
    }

    public function test_a_teacher_makes_codes_within_the_limits_and_sees_only_seats_used(): void
    {
        $this->freezeTime();
        $this->open();
        $this->getJson('/api/auth/config')->assertJsonPath('data.schools', true);
        $this->actingAs($this->teacher(['email_verified_at' => null]));
        $this->assertMatchesContract($this->postJson('/api/school/classes', ['name' => 'Period 3', 'seats' => 30]), 'school.classes.create', 403);
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/school/classes', ['name' => 'Period 3', 'seats' => 30])->assertForbidden()->assertJsonPath('code', 'adult_required');

        $teacher = $this->teacher();
        $this->actingAs($teacher);
        $this->postJson('/api/school/classes', ['name' => 'Period 3', 'seats' => 61])->assertJsonValidationErrors('seats');
        $made = $this->postJson('/api/school/classes', ['name' => 'Period 3 choir', 'seats' => 30]);
        $this->assertMatchesContract($made, 'school.classes.create', 201);
        // Waiting for Admin's approval (Hillel): its code doesn't work yet.
        $made->assertJsonPath('data.used', 0)->assertJsonPath('data.status', 'pending');
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/', (string) $made->json('data.code'));
        $this->assertStringEndsWith('/class/'.$made->json('data.code'), (string) $made->json('data.url'));

        // 150 open seats at most: 30 + 60 + 60 fits, one more seat doesn't.
        $this->postJson('/api/school/classes', ['name' => 'B', 'seats' => 60])->assertCreated();
        $this->postJson('/api/school/classes', ['name' => 'C', 'seats' => 60])->assertCreated();
        $this->assertMatchesContract($this->postJson('/api/school/classes', ['name' => 'D', 'seats' => 1]), 'school.classes.create', 409);

        // Before approval a student's try reads as no code; once approved it works.
        $student = User::factory()->create(['email' => 'student@example.com', 'name' => 'Sam Student']);
        $this->actingAs($student);
        $this->postJson('/api/school/redeem', ['code' => (string) $made->json('data.code')])->assertNotFound()->assertJsonPath('code', 'school_code_unknown');
        $admin = $this->actingAsAdmin();
        $approved = $this->patchJson('/api/admin/school/teachers/'.$teacher->sqid(), ['approved' => true]);
        $this->assertMatchesContract($approved, 'admin.school.teachers.update', 200);
        $approved->assertJsonPath('data.approved', true)->assertJsonPath('data.seatAllowance', 300)->assertJsonPath('data.seatsTaken', 150);

        // A student redeems; the teacher sees a seat used, a day later, and nothing else.
        $this->actingAs($student);
        $redeemed = $this->postJson('/api/school/redeem', ['code' => strtolower(str_replace('-', ' ', (string) $made->json('data.code')))]);
        $this->assertMatchesContract($redeemed, 'school.redeem', 200);
        $redeemed->assertJsonPath('data.plan', 'plus')->assertJsonPath('data.source', 'school')
            ->assertJsonPath('data.schoolUntil', now()->addDays(365)->utc()->toIso8601ZuluString());
        $this->assertTrue(app(Plans::class)->isPlus($student->refresh()));

        $this->actingAs($teacher);
        $list = $this->getJson('/api/school/classes');
        $this->assertMatchesContract($list, 'school.classes.list', 200);
        // The same day: the count hasn't moved, so watching it can't tell them who joined.
        $list->assertJsonPath('data.2.used', 0)->assertJsonPath('data.2.status', 'open');
        $this->travel(1)->days();
        $this->getJson('/api/school/classes')->assertJsonPath('data.2.used', 1);
        $this->assertStringNotContainsString('student@example.com', (string) $list->getContent());
        $this->assertStringNotContainsString('Sam Student', (string) $list->getContent());

        // Seats never below seats used; closing stops new students; someone else's code is not theirs.
        $id = (string) $made->json('data.id');
        $this->patchJson("/api/school/classes/{$id}", ['seats' => 0])->assertJsonValidationErrors('seats');
        $class = SchoolClass::query()->where('code', str_replace('-', '', (string) $made->json('data.code')))->firstOrFail();
        SchoolRedemption::factory()->create(['school_class_id' => $class->id]);
        $class->increment('used'); // a second student, as a redeem counts it
        // Today the teacher still sees one, so one seat is taken (no telling who just joined): the
        // code takes nobody more, and it still holds both seats of the allowance.
        $this->assertMatchesContract($this->patchJson("/api/school/classes/{$id}", ['seats' => 1]), 'school.classes.update', 200);
        $this->assertSame(2, $class->refresh()->allowanceTaken());
        $this->assertSame('full', $class->status());
        $this->travel(1)->days();
        $this->patchJson("/api/school/classes/{$id}", ['seats' => 3])->assertOk();
        $this->assertMatchesContract($this->patchJson("/api/school/classes/{$id}", ['seats' => 1]), 'school.classes.update', 409);
        $this->patchJson("/api/school/classes/{$id}", ['seats' => 1])->assertJsonPath('code', 'school_seats_below_used');
        $this->assertMatchesContract($this->patchJson("/api/school/classes/{$id}", ['seats' => 2, 'name' => 'Renamed']), 'school.classes.update', 200);
        $closed = $this->patchJson("/api/school/classes/{$id}", ['action' => 'close']);
        $this->assertMatchesContract($closed, 'school.classes.update', 200);
        $closed->assertJsonPath('data.status', 'closed')->assertJsonPath('data.name', 'Renamed');
        $this->actingAs($this->teacher());
        $this->assertMatchesContract($this->patchJson("/api/school/classes/{$id}", ['name' => 'Mine now']), 'school.classes.update', 404);
    }

    public function test_a_code_is_redeemed_once_per_account_never_on_top_of_plus_and_never_past_its_seats(): void
    {
        $this->open();
        $class = SchoolClass::factory()->create(['teacher_id' => $this->teacher()->id, 'seats' => 1]);
        $code = $class->shown();

        $this->actingAs($class->teacher)->postJson('/api/school/redeem', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'school_own_code');
        $first = User::factory()->create();
        $this->actingAs($first)->postJson('/api/school/redeem', ['code' => $code])->assertOk();
        $this->assertMatchesContract($this->postJson('/api/school/redeem', ['code' => $code]), 'school.redeem', 409);
        $this->postJson('/api/school/redeem', ['code' => $code])->assertJsonPath('code', 'school_already_redeemed');

        $this->actingAs(User::factory()->create());
        $this->postJson('/api/school/redeem', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'school_code_full');

        // A student who deletes their account keeps the seat used: a new account can't take it again.
        $first->delete();
        $this->assertSame(0, SchoolRedemption::query()->count());
        $this->assertSame(1, $class->refresh()->used);
        $this->postJson('/api/school/redeem', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'school_code_full');
        $this->assertMatchesContract($this->postJson('/api/school/redeem', ['code' => 'ZZZZ-ZZZZ']), 'school.redeem', 404);

        // Expired and closed codes read as unknown; a deleted teacher's code is closed.
        $open = SchoolClass::factory()->create(['teacher_id' => $this->teacher()->id]);
        $expired = SchoolClass::factory()->create(['teacher_id' => $this->teacher()->id, 'expires_at' => now()->subDay()]);
        $orphan = SchoolClass::factory()->create(['teacher_id' => null]);
        foreach ([$expired, $orphan] as $gone) {
            $this->postJson('/api/school/redeem', ['code' => $gone->code])->assertNotFound()->assertJsonPath('code', 'school_code_unknown');
        }

        // Already on Plus: told to keep their own.
        foreach ([['plan' => 'plus'], ['plus_paid_until' => now()->addMonth()], ['store_plus_until' => now()->addMonth()]] as $plus) {
            $this->actingAs(User::factory()->create($plus));
            $this->postJson('/api/school/redeem', ['code' => $open->code])->assertStatus(409)->assertJsonPath('code', 'school_already_plus');
        }
        $this->assertSame(0, SchoolRedemption::query()->count());

        // A school year under another Plus that came later: billing names the one in use, and only a
        // school year in use shows its end.
        $both = User::factory()->create();
        $this->actingAs($both)->postJson('/api/school/redeem', ['code' => $open->code])->assertOk()->assertJsonPath('data.source', 'school');
        $both->forceFill(['plus_paid_until' => now()->addMonths(2)])->save();
        $this->getJson('/api/billing')->assertJsonPath('data.source', 'invoice')->assertJsonPath('data.schoolUntil', null);
    }

    public function test_a_teacher_has_a_lifetime_seat_allowance_that_admin_can_raise(): void
    {
        $this->open();
        $teacher = $this->teacher();
        $this->actingAsAdmin();
        $this->patchJson('/api/admin/school/teachers/'.$teacher->sqid(), ['approved' => true, 'seatAllowance' => 40, 'reason' => 'A small choir'])->assertOk();

        $this->actingAs($teacher);
        $first = $this->postJson('/api/school/classes', ['name' => 'A', 'seats' => 30])->assertCreated();
        $this->assertMatchesContract($this->postJson('/api/school/classes', ['name' => 'B', 'seats' => 20]), 'school.classes.create', 409);
        $this->postJson('/api/school/classes', ['name' => 'B', 'seats' => 20])->assertJsonPath('code', 'school_seat_allowance');
        // Raising a code's seats past it is refused too.
        $this->patchJson('/api/school/classes/'.$first->json('data.id'), ['seats' => 41])->assertStatus(409)->assertJsonPath('code', 'school_seat_allowance');

        // A closed code keeps only the seats it used (none here), so the allowance comes back.
        $this->patchJson('/api/school/classes/'.$first->json('data.id'), ['action' => 'close'])->assertOk();
        $this->postJson('/api/school/classes', ['name' => 'B', 'seats' => 20])->assertCreated();
    }

    public function test_a_teacher_hears_once_that_their_codes_work(): void
    {
        Mail::fake();
        $this->open();
        $teacher = $this->teacher();
        $admin = $this->actingAsAdmin();
        $approve = fn (bool $yes) => $this->patchJson('/api/admin/school/teachers/'.$teacher->sqid(), ['approved' => $yes])->assertOk();

        // A seat allowance alone tells no one.
        $this->patchJson('/api/admin/school/teachers/'.$teacher->sqid(), ['seatAllowance' => 40])->assertOk();
        Mail::assertNothingQueued();

        $approve(true);
        Mail::assertQueued(ClassCodesReady::class, fn (ClassCodesReady $mail): bool => $mail->hasTo((string) $teacher->email));
        // Taken back and given again, or approved twice: never a second email.
        $approve(false);
        $approve(true);
        $approve(true);
        Mail::assertQueuedCount(1);

        // A teacher whose address isn't confirmed isn't emailed (nor marked told, so a later approval still can).
        $unconfirmed = $this->teacher(['email_verified_at' => null]);
        $this->actingAsAdmin($admin);
        $this->patchJson('/api/admin/school/teachers/'.$unconfirmed->sqid(), ['approved' => true])->assertOk();
        Mail::assertQueuedCount(1);
    }

    public function test_guessing_codes_is_limited(): void
    {
        $this->open();
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/school/redeem', ['code' => 'ZZZZ-ZZZ'.$i])->assertNotFound();
        }

        $this->assertMatchesContract($this->postJson('/api/school/redeem', ['code' => 'ZZZZ-ZZZZ']), 'school.redeem', 429);
    }

    public function test_admin_sees_who_and_can_close_a_code_or_end_a_year(): void
    {
        $this->open();
        $class = SchoolClass::factory()->create(['teacher_id' => $this->teacher(['name' => 'Ms Rivera'])->id]);
        $student = User::factory()->create();
        $this->actingAs($student)->postJson('/api/school/redeem', ['code' => $class->code])->assertOk();

        $admin = $this->actingAsAdmin();
        $list = $this->getJson('/api/admin/school');
        $this->assertMatchesContract($list, 'admin.school.list', 200);
        $list->assertJsonPath('data.0.teacher.name', 'Ms Rivera')->assertJsonPath('data.0.used', 1);
        $show = $this->getJson('/api/admin/school/'.$class->sqid());
        $this->assertMatchesContract($show, 'admin.school.show', 200);
        $show->assertJsonPath('data.redemptions.0.user.id', $student->sqid());

        $closed = $this->patchJson('/api/admin/school/'.$class->sqid(), ['action' => 'close', 'reason' => 'Posted on a forum']);
        $this->assertMatchesContract($closed, 'admin.school.update', 200);
        $closed->assertJsonPath('data.closedBy', 'admin');

        $redemption = SchoolRedemption::query()->firstOrFail();
        $ended = $this->postJson('/api/admin/school/redemptions/'.$redemption->sqid().'/end', ['reason' => 'Not a student']);
        $this->assertMatchesContract($ended, 'admin.school.redemptions.end', 200);
        $this->assertFalse(app(Plans::class)->isPlus($student->refresh()));
        $this->assertSame(['school.code_closed', 'school.year_ended'], AuditLog::query()->where('action', 'like', 'school.%')->orderBy('id')->pluck('action')->all());
        $this->assertSame($admin->id, AuditLog::query()->where('action', 'school.year_ended')->value('actor_id'));

        // Not for anyone else.
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/school')->assertForbidden();
    }
}
