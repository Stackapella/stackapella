<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Actions\Beta\RecordConsent;
use App\Enums\CreditMode;
use App\Exceptions\Refusal;
use App\Models\BetaSinger;
use App\Models\User;
use App\Support\Accounts\Age;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "When were you born?" (contract/api.md, auth.register and account.update): a month and year,
 * a birthday counted as the last day of its month, under 13 refused, 18 and over an adult, and a
 * 17-year-old becomes one on their own.
 */
final class AgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-10-27 12:00:00');
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function form(array $with = []): array
    {
        return ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'elapsedMs' => 45000, 'nickname' => '', ...$with];
    }

    public function test_a_birthday_counts_as_the_last_day_of_its_month(): void
    {
        $this->assertFalse(Age::isAtLeast(10, 2008, 18, now()->setDate(2026, 10, 30)));
        $this->assertTrue(Age::isAtLeast(10, 2008, 18, now()->setDate(2026, 10, 31)));
        $this->assertTrue(Age::isAtLeast(2, 2008, 18, now()->setDate(2026, 2, 28)));
        $this->assertFalse(Age::isAtLeast(2, 2008, 18, now()->setDate(2026, 2, 27)));
    }

    public function test_signing_up_with_a_birth_month_and_year(): void
    {
        $this->postJson('/api/auth/register', $this->form(['birthMonth' => 5, 'birthYear' => 1990]))->assertStatus(202);
        $ada = User::query()->sole();
        $this->assertSame([5, 1990], [$ada->birth_month, $ada->birth_year]);
        $this->assertNotNull($ada->age_confirmed_at);
        $this->assertNotNull($ada->adult_confirmed_at);

        // 15: an account, not an adult.
        $this->postJson('/api/auth/register', $this->form(['email' => 'teen@example.com', 'birthMonth' => 3, 'birthYear' => 2011]))->assertStatus(202);
        $teen = User::query()->where('email', 'teen@example.com')->sole();
        $this->assertNotNull($teen->age_confirmed_at);
        $this->assertNull($teen->adult_confirmed_at);

        // Under 13: refused with the code alone, and nothing kept.
        $young = $this->postJson('/api/auth/register', $this->form(['email' => 'kid@example.com', 'birthMonth' => 1, 'birthYear' => 2015]));
        $this->assertMatchesContract($young, 'auth.register', 422);
        $young->assertExactJson(['message' => 'Unprocessable Content.', 'code' => 'too_young']);
        $this->assertFalse(User::query()->where('email', 'kid@example.com')->exists());

        // The old "13 or over" is gone (Hillel: "drop old 13+ answer"); neither at all is a 422.
        $old = $this->form(['email' => 'old@example.com', 'ageConfirmed' => true]);
        unset($old['birthMonth'], $old['birthYear']);
        $this->postJson('/api/auth/register', $old)->assertStatus(422)->assertJsonValidationErrors(['birthMonth', 'ageConfirmed']);
        $this->assertFalse(User::query()->where('email', 'old@example.com')->exists());
        $this->postJson('/api/auth/register', $this->form(['email' => 'none@example.com']))->assertStatus(422)->assertJsonValidationErrors('birthMonth');
    }

    public function test_an_account_gives_it_once_and_the_answer_is_never_sent_back(): void
    {
        $kim = User::factory()->create(['age_confirmed_at' => null, 'adult_confirmed_at' => null])->fresh() ?? throw new \LogicException;
        $this->actingAs($kim);
        $this->assertMatchesContract($this->patchJson('/api/account', ['birthMonth' => 1, 'birthYear' => 2016]), 'account.update', 422);
        $this->assertNull($kim->refresh()->birth_year);

        $set = $this->patchJson('/api/account', ['birthMonth' => 4, 'birthYear' => 2009]);
        $this->assertMatchesContract($set, 'account.update', 200);
        $set->assertJsonPath('data.birthGiven', true)->assertJsonPath('data.isAdult', false)->assertJsonMissingPath('data.ageConfirmed')
            ->assertJsonMissingPath('data.birthYear')->assertJsonMissingPath('data.birthMonth');

        // A later answer doesn't replace it.
        $this->patchJson('/api/account', ['birthMonth' => 1, 'birthYear' => 1980])->assertOk()->assertJsonPath('data.isAdult', false);
        $this->assertSame(2009, $kim->refresh()->birth_year);
    }

    public function test_saying_18_never_overrules_a_birth_date_and_turning_18_needs_no_asking(): void
    {
        $sam = User::factory()->create(['plan' => 'plus', 'adult_confirmed_at' => null, 'birth_month' => 11, 'birth_year' => 2008])->fresh() ?? throw new \LogicException;
        $this->actingAs($sam);
        $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertStatus(403)->assertJsonPath('code', 'adult_required');

        // November 2026 ends: 18.
        $this->artisan('app:accounts:ages')->expectsOutputToContain('0 accounts')->assertSuccessful();
        $this->travelTo('2026-11-30 00:30:00');
        $this->artisan('app:accounts:ages')->expectsOutputToContain('1 accounts')->assertSuccessful();
        $this->assertNotNull($sam->refresh()->adult_confirmed_at);
        $this->getJson('/api/account')->assertJsonPath('data.isAdult', true);
        $this->postJson('/api/groups', ['name' => 'Thursday Singers'])->assertCreated();

        // Without a birth date, "I'm 18" still counts, as before.
        $lee = User::factory()->create(['plan' => 'plus', 'adult_confirmed_at' => null])->fresh() ?? throw new \LogicException;
        $this->actingAs($lee)->postJson('/api/groups', ['name' => 'Lee Singers', 'adult' => true])->assertCreated();
    }

    public function test_the_beta_agreement_never_overrules_a_younger_birth_date(): void
    {
        $teen = User::factory()->create(['birth_month' => 6, 'birth_year' => 2011, 'adult_confirmed_at' => null]);
        $singer = BetaSinger::factory()->create(['user_id' => $teen->id]);

        $this->expectException(Refusal::class);
        app(RecordConsent::class)->handle($singer->fresh() ?? $singer, (int) config('beta.agreement_version'), CreditMode::cases()[0]);
    }
}
