<?php

declare(strict_types=1);

namespace Tests\Feature\Voices;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Enums\TemplateAccess;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\CourseClip;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use App\Support\Templates\Catalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lend your voice (docs/lend-your-voice-plan.md, slice 1): the Wanted voices, joining as an unpaid
 * volunteer with its own agreement, and claims held a while; then it's the beta singers' pipeline.
 */
final class LendVoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();
        // About the live versions: a staged next one in the content is set aside (StagedVersionTest covers it).
        Template::query()->update(['staged_version_id' => null]);
        CourseClip::factory()->create(['slot' => 'yodel.flip', 'course_id' => 'yodeling', 'brief' => ['sing' => ['en' => 'Flip on "yo-dl"'], 'pitch' => null, 'seconds' => 4.0, 'style' => 'yodel', 'voices' => 'any', 'used' => [], 'skill' => 'yodel']]);
        CourseClip::factory()->create(['slot' => 'vibrato.held', 'brief' => ['sing' => ['en' => 'One held "ah"'], 'pitch' => null, 'seconds' => 3.0, 'style' => 'vibrato', 'voices' => 'any', 'used' => [], 'skill' => 'vibrato']]);
    }

    private function singer(string $name = 'Ana Ruiz', array $with = []): User
    {
        $user = User::factory()->create(['name' => $name, 'adult_confirmed_at' => now(), 'birth_year' => 1990, 'birth_month' => 5, 'created_at' => now()->subDays(30), ...$with])->fresh() ?? throw new \LogicException;
        $this->actingAs($user);

        return $user;
    }

    private function join(): void
    {
        $this->postJson('/api/voices/join', ['agreementVersion' => (int) config('beta.volunteer_agreement_version'), 'credit' => 'name', 'linksProfile' => false])->assertOk();
    }

    public function test_it_is_closed_until_the_switch_is_on(): void
    {
        $this->singer();
        $this->assertMatchesContract($this->getJson('/api/voices/wanted'), 'voices.wanted', 404);
        $this->getJson('/api/auth/config')->assertJsonPath('data.lendVoice', false);
        // Claims and sharing too: the open takes' rules apply only while it's on (invited singers' parts are Admin's).
        $this->postJson('/api/voices/claims', ['item' => 'song:amazing-grace:alto'])->assertNotFound();
        $this->assertSame(0, BetaAssignment::query()->count());
    }

    public function test_the_wanted_list_is_parts_and_clips_with_no_real_voice_by_skill(): void
    {
        app(Flags::class)->set('lend_voice', 'on', null, 'Open');
        $this->singer();

        $wanted = $this->getJson('/api/voices/wanted');
        $this->assertMatchesContract($wanted, 'voices.wanted', 200);
        $ids = array_column((array) $wanted->json('data.items'), 'id');
        $this->assertContains('song:amazing-grace:tenor', $ids);
        $this->assertContains('clip:yodel.flip', $ids);
        $yodel = (array) $this->getJson('/api/voices/wanted?skill=yodel')->json('data.items');
        $this->assertContains('clip:yodel.flip', array_column($yodel, 'id'));
        $this->assertSame(['yodel'], array_values(array_unique(array_column($yodel, 'skill'))));
        $this->assertNotContains('clip:yodel.flip', array_column((array) $this->getJson('/api/voices/wanted?kind=songs')->json('data.items'), 'id'));
    }

    public function test_only_an_adult_with_a_confirmed_email_past_their_first_week_joins(): void
    {
        app(Flags::class)->set('lend_voice', 'on', null, 'Open');
        $this->singer('New Comer', ['created_at' => now()->subDays(2)]);
        $this->assertMatchesContract($this->postJson('/api/voices/join', ['agreementVersion' => (int) config('beta.volunteer_agreement_version'), 'credit' => 'name']), 'voices.join', 403);
        $this->singer('No Email', ['email_verified_at' => null]);
        $this->postJson('/api/voices/join', ['agreementVersion' => (int) config('beta.volunteer_agreement_version'), 'credit' => 'name'])->assertForbidden()->assertJsonPath('code', 'email_unverified');
        $this->singer('Not Said', ['adult_confirmed_at' => null]);
        $this->postJson('/api/voices/join', ['agreementVersion' => (int) config('beta.volunteer_agreement_version'), 'credit' => 'name'])->assertForbidden();

        $paid = $this->singer('Paid Singer');
        BetaSinger::factory()->create(['user_id' => $paid->id, 'accepted_at' => now()]);
        $this->assertMatchesContract($this->postJson('/api/voices/join', ['agreementVersion' => (int) config('beta.volunteer_agreement_version'), 'credit' => 'name']), 'voices.join', 409);

        $this->singer('Ana Ruiz');
        $this->assertMatchesContract($this->postJson('/api/voices/join', ['agreementVersion' => (int) config('beta.volunteer_agreement_version') + 1, 'credit' => 'name']), 'voices.join', 409);
        $joined = $this->postJson('/api/voices/join', ['agreementVersion' => (int) config('beta.volunteer_agreement_version'), 'credit' => 'anonymous']);
        $this->assertMatchesContract($joined, 'voices.join', 200);
        $joined->assertJsonPath('data.volunteer', true)->assertJsonPath('data.consent.version', (int) config('beta.volunteer_agreement_version'))->assertJsonPath('data.consent.current', (int) config('beta.volunteer_agreement_version'));
        $this->getJson('/api/beta/agreement?for=volunteer')->assertOk()->assertJsonPath('data.title', 'Lend your voice');
    }

    public function test_a_claim_is_one_persons_for_a_while_and_then_back_on_the_list(): void
    {
        app(Flags::class)->set('lend_voice', 'on', null, 'Open');
        $ana = $this->singer();
        $this->assertMatchesContract($this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip']), 'voices.claims.create', 403);
        $this->join();

        $made = $this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip']);
        $this->assertMatchesContract($made, 'voices.claims.create', 201);
        $made->assertJsonPath('data.ending', false)->assertJsonPath('data.take', null);
        $this->assertMatchesContract($this->getJson('/api/voices/claims'), 'voices.claims.list', 200);
        // It's theirs to record, as a beta singer's part, and nobody else's to claim.
        $this->getJson('/api/beta/parts')->assertOk()->assertJsonCount(1, 'data');
        $this->assertNotContains('clip:yodel.flip', array_column((array) $this->getJson('/api/voices/wanted')->json('data.items'), 'id'));

        $this->singer('Ben Ode');
        $this->join();
        $this->assertMatchesContract($this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip']), 'voices.claims.create', 409);
        // A song's part is an open take (docs/open-guide-voices-plan.md): no limit, never anyone's alone, one a part a person.
        foreach (['song:amazing-grace:alto', 'song:amazing-grace:tenor', 'song:amazing-grace:bass'] as $item) {
            $this->postJson('/api/voices/claims', ['item' => $item])->assertCreated();
        }
        $this->postJson('/api/voices/claims', ['item' => 'song:amazing-grace:alto'])->assertSuccessful();
        $this->assertSame(1, BetaAssignment::query()->where('part_key', 'alto')->whereNotNull('claimed_at')->count(), 'the same open take, not a second');
        $this->assertContains('song:amazing-grace:alto', array_column((array) $this->getJson('/api/voices/wanted')->json('data.items'), 'id'), 'still wanted: others may sing it too');
        // Song parts never count toward the three lesson clips a singer may hold.
        $this->postJson('/api/voices/claims', ['item' => 'clip:vibrato.held'])->assertCreated();

        // Given back, it's wanted again.
        $this->actingAs($ana);
        $this->assertMatchesContract($this->deleteJson('/api/voices/claims/'.BetaAssignment::query()->whereHas('clip', fn ($q) => $q->where('slot', 'yodel.flip'))->sole()->sqid()), 'voices.claims.delete', 204);
        $this->assertContains('clip:yodel.flip', array_column((array) $this->getJson('/api/voices/wanted')->json('data.items'), 'id'));

        // A claim says it's ending from day 10, and runs out at 14 with nothing sent.
        $this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip'])->assertCreated();
        $this->travel(11)->days();
        $this->getJson('/api/voices/claims')->assertJsonPath('data.0.ending', true);
        $this->travel(4)->days();
        $this->getJson('/api/voices/claims')->assertJsonCount(0, 'data');
        $this->artisan('app:voices:release')->expectsOutputToContain('Released')->assertSuccessful();
        $this->assertContains('clip:yodel.flip', array_column((array) $this->getJson('/api/voices/wanted')->json('data.items'), 'id'));
    }

    public function test_a_beta_singer_opens_every_song_with_their_account_claimed_or_not(): void
    {
        // Every library song is free with an account (2026-11-07): a claim gives nothing extra.
        $ana = $this->singer();
        $singer = BetaSinger::factory()->create(['user_id' => $ana->id, 'accepted_at' => now()]);
        $song = Template::query()->official()->where('access', TemplateAccess::Plus)->firstOrFail();
        $this->assertSame('account', app(Catalogue::class)->grant($song, $ana));

        BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'template_id' => $song->id, 'claimed_at' => now(), 'expires_at' => now()]);
        $this->assertSame('account', app(Catalogue::class)->grant($song, $ana));
    }

    public function test_claims_are_limited_to_six_a_day(): void
    {
        app(Flags::class)->set('lend_voice', 'on', null, 'Open');
        $this->singer();
        $this->join();

        foreach (range(1, 6) as $n) {
            $this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip'])->assertCreated();
            $this->deleteJson('/api/voices/claims/'.BetaAssignment::query()->whereNotNull('course_clip_id')->sole()->sqid())->assertNoContent();
            $this->artisan('app:voices:release')->assertSuccessful();
        }

        $over = $this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip']);
        $this->assertMatchesContract($over, 'voices.claims.create', 429);
        $over->assertJsonPath('code', 'rate_limited');
    }
}
