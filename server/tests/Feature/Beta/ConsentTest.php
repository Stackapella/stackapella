<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Models\BetaSinger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

final class ConsentTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_consent_records_the_version_and_the_credit(): void
    {
        $singer = BetaSinger::factory()->create();

        $response = $this->asSinger($singer)->postJson('/api/beta/consent', ['version' => 1, 'adult' => true, 'creditMode' => 'name']);

        // The credit is the account's name.
        $this->assertMatchesContract($response, 'beta.consent.create', 200);
        $response->assertJsonPath('data.consent.version', 1)->assertJsonPath('data.credit.name', 'Sarah K.');
        $this->assertTrue($singer->refresh()->hasConsented(1));
    }

    public function test_consent_needs_18_the_current_agreement_and_a_credit_choice(): void
    {
        $singer = BetaSinger::factory()->create();

        $young = $this->asSinger($singer)->postJson('/api/beta/consent', ['version' => 1, 'adult' => false, 'creditMode' => 'anonymous']);
        $this->assertMatchesContract($young, 'beta.consent.create', 422);
        $young->assertJsonPath('errors.adult.0', __('validation.adult'));

        $this->assertMatchesContract($this->asSinger($singer)->postJson('/api/beta/consent', ['version' => 0, 'adult' => true, 'creditMode' => 'anonymous']), 'beta.consent.create', 409);

        $this->assertMatchesContract($this->asSinger($singer)->postJson('/api/beta/consent', ['version' => 1, 'adult' => true, 'creditMode' => 'nickname']), 'beta.consent.create', 422);

        $this->assertFalse($singer->refresh()->hasConsented(1));
    }

    public function test_the_credit_needs_consent_first(): void
    {
        $singer = BetaSinger::factory()->create();

        $this->assertMatchesContract($this->asSinger($singer)->putJson('/api/beta/credit', ['creditMode' => 'anonymous']), 'beta.credit.update', 403);
    }

    public function test_the_credit_changes_at_any_time(): void
    {
        $singer = BetaSinger::factory()->consented()->create();

        $response = $this->asSinger($singer)->putJson('/api/beta/credit', ['creditMode' => 'anonymous']);
        $this->assertMatchesContract($response, 'beta.credit.update', 200);
        $this->assertNull($singer->refresh()->credit());

        $this->assertMatchesContract($this->asSinger($singer)->putJson('/api/beta/credit', ['creditMode' => 'loud']), 'beta.credit.update', 422);
    }

    public function test_a_renamed_profile_is_the_new_credit_at_once(): void
    {
        $singer = BetaSinger::factory()->consented()->create();
        $this->asSinger($singer)->patchJson('/api/account', ['name' => 'Sarah Kim'])->assertOk();

        $this->getJson('/api/beta/me')->assertJsonPath('data.credit.name', 'Sarah Kim');
    }
}
