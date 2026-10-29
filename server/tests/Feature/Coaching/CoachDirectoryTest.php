<?php

declare(strict_types=1);

namespace Tests\Feature\Coaching;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\AuditLog;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EventScheduling;
use Tests\TestCase;

/**
 * The coach directory (N4; contract/api.md, "The coach directory (N4)"): approved coaches who
 * can take payment, filtered, suggested and featured, their prices, turnaround and next lesson
 * worked out hourly.
 */
final class CoachDirectoryTest extends TestCase
{
    use EventScheduling;
    use RefreshDatabase;

    private function open(): void
    {
        app(Flags::class)->set('coach_directory', 'on', null, 'Opening the directory');
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function reviewer(string $handle, array $profile = [], int $cents = 2500, int $days = 5): User
    {
        $coach = User::factory()->create(['name' => ucfirst($handle), 'handle' => $handle, 'coach_active_at' => now()]);
        CoachProfile::factory()->create(['user_id' => $coach->id, ...$profile]);
        CoachOffering::factory()->create(['coach_id' => $coach->id, 'price_cents' => $cents, 'turnaround_days' => $days, 'currency' => 'usd']);

        return $coach;
    }

    /**
     * @return list<string>
     */
    private function handles(string $query = ''): array
    {
        return array_column((array) $this->getJson('/api/coaches'.($query !== '' ? '?'.$query : ''))->assertOk()->json('data'), 'handle');
    }

    public function test_the_directory_is_closed_until_its_flag_opens(): void
    {
        $this->reviewer('grace');

        $this->assertMatchesContract($this->getJson('/api/coaches'), 'coaches.list', 404);
        $this->get('/coaches')->assertNotFound();
        // A coach's own link works as before.
        $this->getJson('/api/coaches/grace')->assertOk();
    }

    public function test_coaches_are_found_by_voice_style_language_price_turnaround_and_rating(): void
    {
        $this->open();
        $this->reviewer('grace', ['voice_types' => ['alto'], 'styles' => ['gospel'], 'languages' => ['en'], 'rating_average' => 4.9, 'rating_count' => 10], 4000, 2);
        $this->reviewer('omar', ['voice_types' => ['bass'], 'styles' => ['choral', 'barbershop'], 'languages' => ['en', 'ar'], 'rating_average' => 4.2, 'rating_count' => 5], 1500, 7);
        $this->reviewer('nell', ['voice_types' => ['soprano'], 'styles' => ['classical'], 'languages' => ['fr']], 2000, 10);
        // Never listed: paused, unable to take payment, or suspended.
        $this->reviewer('paused', ['status' => CoachProfile::PAUSED]);
        $this->reviewer('unpaid', ['charges_enabled' => false]);
        $this->reviewer('gone')->forceFill(['suspended_at' => now()])->save();
        $this->artisan('app:coaches:refresh')->expectsOutputToContain('Refreshed 5')->assertSuccessful();

        $list = $this->getJson('/api/coaches?sort=rating');
        $this->assertMatchesContract($list, 'coaches.list', 200);
        $this->assertSame(['grace', 'omar', 'nell'], array_column((array) $list->json('data'), 'handle'));
        $list->assertJsonPath('data.0.from', ['cents' => 4000, 'currency' => 'usd'])->assertJsonPath('data.0.turnaroundDays', 2)->assertJsonPath('data.2.newCoach', true)
            ->assertJsonPath('meta.styles', ['barbershop', 'choral', 'classical', 'gospel'])->assertJsonPath('meta.languages', ['ar', 'en', 'fr']);

        $this->assertSame(['grace'], $this->handles('voiceType=alto'));
        $this->assertSame(['omar'], $this->handles('style=barbershop'));
        $this->assertSame(['omar'], $this->handles('language=ar'));
        $this->assertSame(['omar', 'nell'], $this->handles('maxPrice=2000&currency=usd&sort=price'));
        $this->assertSame(['grace', 'omar'], $this->handles('turnaround=7&sort=rating'));
        $this->assertSame(['grace', 'omar'], $this->handles('rating=4&sort=rating'));
        $this->assertSame(['omar', 'nell', 'grace'], $this->handles('sort=price'));
        $this->assertMatchesContract($this->getJson('/api/coaches?sort=cheapest'), 'coaches.list', 422);

        // Suggested: featured first, then the singer's voice type, then the song's styles.
        app(SyncOfficialTemplates::class)->handle();
        $this->assertSame('nell', $this->handles('voiceType=soprano&sort=suggested')[0] ?? null);
        $this->assertSame('grace', $this->handles('song=amazing-grace')[0] ?? null);

        // Featured by Hillel, in sudo.
        $admin = $this->actingAsAdmin();
        $featured = $this->putJson('/api/admin/coaches/nell/feature', ['featured' => true]);
        $this->assertMatchesContract($featured, 'admin.coaches.feature', 200);
        $featured->assertJsonPath('data.featured', true);
        $this->assertMatchesContract($this->putJson('/api/admin/coaches/paused/feature', ['featured' => true]), 'admin.coaches.feature', 404);
        $this->assertMatchesContract($this->putJson('/api/admin/coaches/nell/feature', []), 'admin.coaches.feature', 422);
        $this->assertTrue(AuditLog::query()->where('action', 'coaches.featured')->where('actor_id', $admin->id)->exists());
        $this->assertSame(['nell'], $this->handles('tab=featured'));
        $this->assertSame('nell', $this->handles()[0] ?? null);

        // Never someone the caller is blocked with.
        $singer = User::factory()->create();
        UserBlock::query()->create(['blocker_id' => $singer->id, 'blocked_id' => User::query()->where('handle', 'omar')->value('id')]);
        $this->actingAs($singer);
        $this->assertNotContains('omar', $this->handles());
    }

    public function test_the_next_lesson_comes_from_event_schedule_and_pages_follow_the_cursor(): void
    {
        $this->open();
        $this->fakeEventSchedule();
        [$coach] = $this->lessonCoach();
        $this->reviewer('omar');
        $this->artisan('app:coaches:refresh')->assertSuccessful();

        $soonest = $this->getJson('/api/coaches?lessons=1');
        $this->assertSame([$coach->handle], array_column((array) $soonest->json('data'), 'handle'));
        $this->assertNotNull($soonest->json('data.0.nextLessonAt'));
        $this->assertSame($coach->handle, $this->handles('sort=soonest')[0] ?? null);

        foreach (range(1, 24) as $i) {
            $this->reviewer('coach'.$i);
        }
        $first = $this->getJson('/api/coaches')->assertJsonCount(24, 'data');
        $this->assertSame('2', $first->json('meta.nextCursor'));
        $this->getJson('/api/coaches?cursor=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.nextCursor', null);
    }
}
