<?php

declare(strict_types=1);

namespace Tests\Feature\School;

use App\Models\OutreachTarget;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Partner codes (docs/outreach-plan.md, "The partner offer"): Admin makes one for a partner
 * organization's members with the seats it needs, no teacher to approve and no lifetime cap; each
 * member redeems it once for a free year (under-18s too, under the school rules); its outreach
 * organization becomes a partner, and outreach's results count the seats used, never who.
 */
final class PartnerCodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Flags::class)->set('schools', 'on', null, 'Open');
    }

    private function member(array $with = []): User
    {
        return User::factory()->create(['adult_confirmed_at' => now(), ...$with]);
    }

    public function test_admin_makes_a_partner_code_its_members_redeem_once_each_for_a_year(): void
    {
        $target = OutreachTarget::factory()->create(['name' => 'Harmony Society', 'status' => 'replied']);
        $admin = $this->actingAsAdmin();

        $this->assertMatchesContract($this->postJson('/api/admin/school/partners', ['name' => 'Members']), 'admin.school.partners.create', 422);
        $made = $this->postJson('/api/admin/school/partners', ['name' => 'Harmony Society members', 'seats' => 2, 'targetId' => $target->sqid()]);
        $this->assertMatchesContract($made, 'admin.school.partners.create', 201);
        $made->assertJsonPath('data.kind', 'partner')->assertJsonPath('data.status', 'open')->assertJsonPath('data.teacher', null)->assertJsonPath('data.partner.name', 'Harmony Society');
        $code = (string) $made->json('data.code');
        $this->assertSame(['partner', 'joined'], [$target->refresh()->status, $target->outcome]);

        // An adult member and a teenage one both redeem it; each gets a year of Plus.
        $this->actingAs($adult = $this->member());
        $this->postJson('/api/school/redeem', ['code' => $code])->assertOk()->assertJsonPath('data.plan', 'plus');
        $this->actingAs($teen = $this->member(['adult_confirmed_at' => null, 'birth_year' => (int) now()->year - 15, 'birth_month' => 1]));
        $this->postJson('/api/school/redeem', ['code' => $code])->assertOk();
        $this->assertNotNull($teen->refresh()->school_plus_until);
        $this->assertTrue($adult->refresh()->school_plus_until->greaterThan(now()->addDays(364)));

        // Its seats are used up; Admin raises them, never below those used.
        $this->actingAs($this->member());
        $this->postJson('/api/school/redeem', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'school_code_full');
        $class = SchoolClass::query()->where('kind', 'partner')->sole();
        $this->actingAsAdmin($admin);
        $this->assertMatchesContract($this->patchJson('/api/admin/school/'.$class->sqid(), ['action' => 'seats', 'seats' => 1]), 'admin.school.update', 409);
        $raised = $this->patchJson('/api/admin/school/'.$class->sqid(), ['action' => 'seats', 'seats' => 19000]);
        $this->assertMatchesContract($raised, 'admin.school.update', 200);
        $raised->assertJsonPath('data.seats', 19000);

        // Outreach sees the seats used, never who.
        $show = $this->getJson('/api/admin/outreach/'.$target->sqid());
        $this->assertMatchesContract($show, 'admin.outreach.show', 200);
        $show->assertJsonPath('data.partnerCodes.0.used', 2)->assertJsonPath('data.followUpDue', false);
        $results = $this->getJson('/api/admin/outreach/results');
        $this->assertMatchesContract($results, 'admin.outreach.results', 200);
        $this->assertSame([1, 2], array_values(array_intersect_key(collect((array) $results->json('data.byGroup'))->firstWhere('key', 'barbershop'), ['partners' => 1, 'redemptions' => 1])));
    }

    public function test_one_code_per_member_ever_and_a_teachers_class_still_needs_approval(): void
    {
        $admin = $this->actingAsAdmin();
        $first = (string) $this->postJson('/api/admin/school/partners', ['name' => 'A', 'seats' => 10])->json('data.code');
        $second = (string) $this->postJson('/api/admin/school/partners', ['name' => 'B', 'seats' => 10, 'days' => 30])->json('data.code');

        $this->actingAs($this->member());
        $this->postJson('/api/school/redeem', ['code' => $first])->assertOk();
        $this->postJson('/api/school/redeem', ['code' => $second])->assertStatus(409)->assertJsonPath('code', 'school_already_redeemed');

        // Seats on a teacher's class aren't Admin's to set this way.
        $class = SchoolClass::factory()->create();
        $this->actingAsAdmin($admin);
        $this->patchJson('/api/admin/school/'.$class->sqid(), ['action' => 'seats', 'seats' => 500])->assertStatus(409)->assertJsonPath('code', 'school_not_partner');
        $this->assertSame('class', $class->refresh()->kind);
        $this->assertSame(1, SchoolClass::query()->where('kind', 'partner')->where('code', '!=', '')->whereDate('expires_at', now()->addDays(30)->toDateString())->count());
    }
}
