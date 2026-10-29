<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Enums\SubmissionStatus;
use App\Mail\Account\VerifyEmailAddress;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Accounts\SignInSession;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Beta singers have accounts (Hillel's decision): an invite attaches the singer record to the
 * account that accepts it, once, and gets that one sign-up through paused sign-ups.
 */
final class InvitesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'an-invite-token-for-sarah-0000000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // The beta closed to strangers, as Hillel set it.
        app(Flags::class)->set('pause_signups', 'all', null, 'Beta only');
    }

    /**
     * As a request loads it: every column (a new factory model has only what it set).
     */
    private function account(string $name = 'Someone', bool $unverified = false): User
    {
        $factory = $unverified ? User::factory()->withPassword()->unverified() : User::factory();

        return $factory->create(['name' => $name])->fresh() ?? throw new \LogicException('No user.');
    }

    private function invite(): BetaSinger
    {
        return BetaSinger::factory()->withToken(self::TOKEN)->create(['name' => 'Sarah Kim', 'expires_at' => now()->addDays(14)]);
    }

    public function test_the_invite_page_says_who_its_for_and_nothing_about_others(): void
    {
        $this->invite();

        $shown = $this->getJson('/api/beta/invites/'.self::TOKEN);
        $this->assertMatchesContract($shown, 'beta.invite.show', 200);
        $shown->assertJsonPath('data', ['name' => 'Sarah Kim', 'status' => 'open', 'bar' => ['pitchPercent' => 90, 'timingPercent' => 80]]);
        $this->assertMatchesContract($this->getJson('/api/beta/invites/nope'), 'beta.invite.show', 404);
    }

    public function test_an_email_sign_up_with_the_invite_gets_through_the_pause_and_is_accepted_at_verification(): void
    {
        $singer = $this->invite();

        $this->postJson('/api/auth/register', ['name' => 'Sarah Kim', 'email' => 'sarah@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'elapsedMs' => 30000])->assertStatus(423);
        $this->postJson('/api/auth/register', ['name' => 'Sarah Kim', 'email' => 'sarah@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'elapsedMs' => 30000, 'invite' => self::TOKEN])->assertStatus(202);

        $token = '';
        Mail::assertQueued(VerifyEmailAddress::class, function (VerifyEmailAddress $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        // Opened in another browser: the server remembered the invite.
        $verified = $this->postJson('/api/auth/verify', ['token' => $token]);
        $this->assertMatchesContract($verified, 'auth.verify', 200);
        $verified->assertJsonPath('data.betaSinger', true);
        $this->assertSame(User::query()->where('email', 'sarah@example.com')->value('id'), $singer->refresh()->user_id);

        $this->assertMatchesContract($this->getJson('/api/beta/me'), 'beta.me.show', 200);
        $this->getJson('/api/beta/invites/'.self::TOKEN)->assertJsonPath('data.status', 'accepted');
    }

    public function test_a_google_sign_up_with_the_invite_is_accepted_at_once(): void
    {
        $singer = $this->invite();
        Socialite::fake('google', (new ProviderUser)->map(['id' => 'g-sarah', 'name' => 'Sarah Kim', 'email' => 'sarah@gmail.example'])->setRaw(['email_verified' => true]));

        $this->post('/auth/google', ['intended' => '/beta', 'invite' => self::TOKEN]);
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/beta?signedIn=google');

        $this->assertSame(User::query()->where('google_id', 'g-sarah')->value('id'), $singer->refresh()->user_id);
    }

    public function test_someone_signed_in_accepts_once_and_an_invite_never_goes_to_a_second_account(): void
    {
        $singer = $this->invite();
        $sarah = $this->account('Sarah Kim');
        $this->actingAs($sarah);

        $accepted = $this->postJson('/api/beta/invites/'.self::TOKEN.'/accept');
        $this->assertMatchesContract($accepted, 'beta.invite.accept', 200);
        $accepted->assertJsonPath('data.name', 'Sarah Kim');
        $this->postJson('/api/beta/invites/'.self::TOKEN.'/accept')->assertOk();
        $this->getJson('/api/account')->assertJsonPath('data.betaSinger', true);

        $this->actingAs($this->account());
        $this->assertMatchesContract($this->postJson('/api/beta/invites/'.self::TOKEN.'/accept'), 'beta.invite.accept', 409);
        $this->assertSame($sarah->id, $singer->refresh()->user_id);
    }

    public function test_an_unverified_expired_or_revoked_invite_cant_be_accepted(): void
    {
        $singer = $this->invite();
        $this->actingAs($this->account(unverified: true));
        $this->assertMatchesContract($this->postJson('/api/beta/invites/'.self::TOKEN.'/accept'), 'beta.invite.accept', 403);

        $this->actingAs($this->account());
        $singer->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertMatchesContract($this->postJson('/api/beta/invites/'.self::TOKEN.'/accept'), 'beta.invite.accept', 410);

        $singer->forceFill(['expires_at' => null, 'revoked_at' => now()])->save();
        $this->postJson('/api/beta/invites/'.self::TOKEN.'/accept')->assertStatus(410)->assertJsonPath('code', 'invite_revoked');
        $this->assertMatchesContract($this->postJson('/api/beta/invites/nope/accept'), 'beta.invite.accept', 404);
        $this->assertNull($singer->refresh()->user_id);
    }

    public function test_one_account_is_one_singer(): void
    {
        $this->invite();
        $existing = BetaSinger::factory()->accepted()->create();
        $this->actingAs(User::query()->findOrFail($existing->user_id)->fresh() ?? throw new \LogicException('No user.'));

        $this->assertMatchesContract($this->postJson('/api/beta/invites/'.self::TOKEN.'/accept'), 'beta.invite.accept', 409);
    }

    public function test_a_revoked_singer_keeps_the_account_but_not_the_beta(): void
    {
        $singer = BetaSinger::factory()->accepted()->consented()->create();
        $this->asSinger($singer)->getJson('/api/beta/me')->assertOk();

        $singer->forceFill(['revoked_at' => now()])->save();

        $this->getJson('/api/beta/me')->assertStatus(403)->assertJsonPath('code', 'singer_revoked');
        $this->getJson('/api/account')->assertOk()->assertJsonPath('data.betaSinger', false);
    }

    public function test_deleting_the_account_takes_the_singers_guides_off_the_app(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $submission = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Accepted]);
        $guide = TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'beta_submission_id' => $submission->id]);
        $user = User::query()->findOrFail($singer->user_id);
        $this->actingAs($user)->withSession([SignInSession::SIGNED_IN_AT => now()->getTimestamp()]);

        $this->deleteJson('/api/account')->assertNoContent();

        $this->assertModelMissing($guide);
        $this->assertNotNull($singer->refresh()->forgotten_at);
    }

    public function test_the_agreement_comes_as_text_blocks_marked_as_a_draft_and_only_signed_in(): void
    {
        // Its blanks are for the singers: never shown to anyone signed out.
        $this->assertSame(401, $this->getJson('/api/beta/agreement')->status());

        $this->actingAs(User::factory()->create());
        $response = $this->getJson('/api/beta/agreement');

        $this->assertMatchesContract($response, 'beta.agreement.show', 200);
        $response->assertJsonPath('data.version', (int) config('beta.agreement_version'))->assertJsonPath('data.reviewed', false);
        $this->assertStringNotContainsString('<', (string) json_encode($response->json('data.blocks')));
        $this->assertContains('heading', array_column((array) $response->json('data.blocks'), 'type'));
    }
}
