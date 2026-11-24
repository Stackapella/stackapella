<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\AiVoice;
use App\Models\BetaSinger;
use App\Models\User;
use App\Support\Accounts\Age;
use App\Support\AiVoices\AiLicence;
use App\Support\Flags;
use App\Support\Stats\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * AI as a choice (docs/ai-optional-plan.md v2): each account's AI features setting (null until chosen, every older
 * account on), where it came from, the age band kept at sign-up, Settings' switches counted by band, Growth's AI choice
 * card, and a lent voice that makes nothing new while its singer has AI off.
 */
final class AiFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 12:00:00', 'UTC'));
        config(['counts.enabled' => true]);
        Mail::fake();
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return DB::table('usage_counts')->where('kind', 'like', 'ai\\_%')->pluck('count', 'kind')->map(static fn (mixed $n): int => (int) $n)->sortKeys()->all();
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function signUp(string $email, array $with = []): User
    {
        $this->postJson('/api/auth/register', ['name' => 'Ada Lovelace', 'email' => $email, 'password' => 'a-fine-long-passphrase-42', 'elapsedMs' => 45000, 'nickname' => '', 'birthMonth' => 5, 'birthYear' => 1999, ...$with])->assertStatus(202);

        return User::query()->where('email', $email)->sole();
    }

    private function person(array $with = []): User
    {
        return User::factory()->create($with)->fresh() ?? throw new \LogicException;
    }

    public function test_a_new_account_takes_the_devices_choice_or_stays_unchosen(): void
    {
        $arrived = $this->signUp('ada@example.com', ['aiFeatures' => true]);
        $this->assertSame([true, 'ai-singing', '25-34'], [$arrived->ai_features, $arrived->ai_features_source, $arrived->age_band]);
        $tried = $this->signUp('bea@example.com', ['aiFeatures' => true, 'aiFeaturesSource' => 'card']);
        $this->assertSame([true, 'card'], [$tried->ai_features, $tried->ai_features_source]);
        $declined = $this->signUp('cal@example.com', ['aiFeatures' => false]);
        $this->assertSame([false, 'card'], [$declined->ai_features, $declined->ai_features_source]);
        $unchosen = $this->signUp('dot@example.com');
        $this->assertSame([null, null], [$unchosen->ai_features, $unchosen->ai_features_source]);
        // A sign-up isn't a switch: the device counts the card itself.
        $this->assertSame([], $this->counts());
        $this->postJson('/api/auth/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'a-fine-long-passphrase-42', 'elapsedMs' => 45000, 'birthMonth' => 5, 'birthYear' => 1999, 'aiFeatures' => true, 'aiFeaturesSource' => 'settings'])->assertStatus(422);

        $this->actingAs($arrived);
        $me = $this->getJson('/api/account');
        $this->assertMatchesContract($me, 'account.show', 200);
        $me->assertJsonPath('data.aiFeatures', true)->assertJsonPath('data.aiFeaturesSource', 'ai-singing')->assertJsonPath('data.ageBand', '25-34');
    }

    public function test_every_account_made_before_keeps_ai_on_and_the_fill_runs_twice_safely(): void
    {
        $older = $this->person(['ai_features' => null, 'ai_features_source' => null, 'age_band' => null, 'birth_month' => 3, 'birth_year' => 1960]);
        $chose = $this->person(['ai_features' => false, 'ai_features_source' => 'settings', 'age_band' => null]);

        $fill = require database_path('migrations/2026_11_24_130000_add_ai_features_to_users.php');
        $fill->up();
        $this->assertSame([true, 'existing', '65+'], [$older->refresh()->ai_features, $older->ai_features_source, $older->age_band]);
        $this->assertSame([false, 'settings'], [$chose->refresh()->ai_features, $chose->ai_features_source]);

        // A second run changes nothing, the older account's choice since included.
        $older->forceFill(['ai_features' => false, 'ai_features_source' => 'settings'])->save();
        $fill->up();
        $this->assertSame([false, 'settings', '65+'], [$older->refresh()->ai_features, $older->ai_features_source, $older->age_band]);
    }

    public function test_the_band_is_kept_from_the_first_birth_date_and_never_moves(): void
    {
        $this->assertSame('u18', Age::band(12, 2008));
        $this->assertSame('18-24', Age::band(10, 2008));
        $this->assertSame('65+', Age::band(1, 1950));

        $ben = $this->person(['birth_month' => null, 'birth_year' => null, 'age_band' => null]);
        $this->actingAs($ben)->patchJson('/api/account', ['birthMonth' => 11, 'birthYear' => 2001])->assertOk();
        $this->assertSame('18-24', $ben->refresh()->age_band);

        $this->travelTo(Carbon::parse('2030-01-01 12:00:00', 'UTC'));
        $this->patchJson('/api/account', ['name' => 'Ben Ode'])->assertOk();
        $this->assertSame('18-24', $ben->refresh()->age_band);
    }

    public function test_only_a_change_in_settings_is_counted_by_band(): void
    {
        $cal = $this->person(['age_band' => '35-44', 'ai_features' => null, 'ai_features_source' => null]);
        $this->actingAs($cal);

        // The card's Try it: saved with its source, counted by the device, not here.
        $saved = $this->patchJson('/api/account', ['aiFeatures' => true, 'aiFeaturesSource' => 'card']);
        $this->assertMatchesContract($saved, 'account.update', 200);
        $saved->assertJsonPath('data.aiFeatures', true);
        $this->assertSame('card', $cal->refresh()->ai_features_source);

        // Then off and on again in Settings; the same answer twice counts once.
        $this->patchJson('/api/account', ['aiFeatures' => false])->assertOk();
        $this->patchJson('/api/account', ['aiFeatures' => false])->assertOk();
        $this->patchJson('/api/account', ['aiFeatures' => true])->assertOk();
        $this->patchJson('/api/account', ['aiFeatures' => null])->assertStatus(422);
        $this->patchJson('/api/account', ['aiFeatures' => true, 'aiFeaturesSource' => 'existing'])->assertStatus(422);

        $this->assertSame(['ai_switch:off_on:35-44' => 1, 'ai_switch:on_off:35-44' => 1], $this->counts());
        $this->assertSame([true, 'settings'], [$cal->refresh()->ai_features, $cal->ai_features_source]);
    }

    public function test_a_lent_voice_makes_nothing_new_while_its_singer_has_ai_off(): void
    {
        $owner = $this->person(['ai_features' => true]);
        $singer = BetaSinger::factory()->create(['user_id' => $owner->id]);
        $voice = AiVoice::factory()->create(['beta_singer_id' => $singer->id, 'consent_version' => (string) AiLicence::current()]);
        $this->assertTrue(AiLicence::licensed($voice->fresh() ?? $voice));

        $owner->forceFill(['ai_features' => false])->save();
        $this->assertFalse(AiLicence::licensed($voice->fresh() ?? $voice), 'Consent must be live.');

        $owner->forceFill(['ai_features' => true])->save();
        $this->assertTrue(AiLicence::licensed($voice->fresh() ?? $voice));

        // A voice added by command was consented outside the app.
        $this->assertTrue(AiLicence::licensed(AiVoice::factory()->create(['beta_singer_id' => null])));
    }

    public function test_growth_shows_the_card_use_and_engagement_by_band_with_small_counts_suppressed(): void
    {
        $count = static fn (string $kind, int $n) => DB::table('usage_counts')->insert(['day' => ReportDay::today(), 'kind' => $kind, 'audience' => 'account', 'country' => '', 'variant' => '', 'source' => '', 'count' => $n]);
        $count('ai_card:shown:25-34', 10);
        $count('ai_card:try:25-34', 4);
        $count('ai_card:not:25-34', 5);
        $count('ai_card:shown:guest', 6);
        $count('ai_card:try:guest', 2);
        $count('ai_use:choir_heard:25-34', 3);
        $count('aic:back_d1:unset:25-34', 7);
        $count('aic:wk1_lessons:off:25-34:1-2', 3);
        $count('ai_switch:on_off:25-34', 1);
        $count('ai_card:shown:unknown', 1);

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/growth/ai');
        $this->assertMatchesContract($shown, 'admin.growth.ai', 200);
        $bands = collect($shown->json('data.bands'))->keyBy('band');
        $this->assertSame([...Age::BANDS, 'guest'], array_column((array) $shown->json('data.bands'), 'band'));
        $this->assertSame([10, 4, 5, 0.4, 0.5, 3], [$bands['25-34']['cardShown'], $bands['25-34']['cardTry'], $bands['25-34']['cardNot'], $bands['25-34']['tryRate'], $bands['25-34']['notRate'], $bands['25-34']['choirHeard']]);
        $this->assertSame([7, 3, null], [$bands['25-34']['engagement'][2]['backD1'], $bands['25-34']['engagement'][1]['lessons1to2'], $bands['25-34']['switchOnOff']]);
        // Two tries is too few, and so is their rate.
        $this->assertSame([6, null, null], [$bands['guest']['cardShown'], $bands['guest']['cardTry'], $bands['guest']['tryRate']]);
        // The total holds every band, guests and the accounts with no birth date.
        $this->assertSame([17, 6, 0.353], [$shown->json('data.total.cardShown'), $shown->json('data.total.cardTry'), $shown->json('data.total.tryRate')]);
        $this->getJson('/api/admin/growth/ai?period=90d')->assertJsonPath('data.period', '90d');
        $this->getJson('/api/admin/growth/ai?period=7d')->assertUnprocessable();

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/growth/ai')->assertForbidden();
    }

    public function test_the_release_switch_reaches_the_app(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('data.aiOptional', false);
        app(Flags::class)->set('ai_optional', 'on', null, 'test');
        $this->getJson('/api/auth/config')->assertJsonPath('data.aiOptional', true);
    }
}
