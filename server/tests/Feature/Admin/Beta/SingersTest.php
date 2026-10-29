<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Beta;

use App\Enums\SubmissionStatus;
use App\Jobs\PublishGuide;
use App\Models\AuditLog;
use App\Models\BetaAssignment;
use App\Models\BetaFeedback;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

final class SingersTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_inviting_a_singer_gives_a_link_once_and_the_parts(): void
    {
        $this->catalogue();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/beta/singers', ['name' => 'Sarah Kim', 'email' => 'sarah@example.com', 'hourlyRateCents' => 3000, 'currency' => 'usd', 'parts' => [['templateId' => 'amazing-grace', 'partKey' => 'alto', 'minutes' => 25]]]);

        $this->assertMatchesContract($response, 'admin.beta.singers.create', 201);
        $response->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.assignments.0.partKey', 'alto')->assertJsonPath('data.link', 'active');
        $token = basename((string) $response->json('link'));
        $this->assertSame(hash('sha256', $token), BetaSinger::query()->firstOrFail()->token_hash, 'Only the hash is kept.');

        $this->flushSession();
        $this->getJson('/api/beta/invites/'.$token)->assertJsonPath('data', ['name' => 'Sarah Kim', 'status' => 'open', 'bar' => ['pitchPercent' => 90, 'timingPercent' => 80]]);
        $this->assertNotNull(BetaSinger::query()->firstOrFail()->expires_at, 'An invite lasts 14 days unless Hillel says otherwise.');
    }

    public function test_the_same_part_twice_in_an_invite_is_refused(): void
    {
        $this->catalogue();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/beta/singers', ['name' => 'Sarah', 'parts' => [['templateId' => 'amazing-grace', 'partKey' => 'alto'], ['templateId' => 'amazing-grace', 'partKey' => 'alto'], ['templateId' => 'silent-night', 'partKey' => 'alto']]]);

        $this->assertMatchesContract($response, 'admin.beta.singers.create', 422);
        $response->assertJsonPath('errors', ['parts.1.partKey' => [__('validation.part_twice')]]);
    }

    public function test_forgetting_a_singer_removes_their_voice_recordings_and_details(): void
    {
        $template = $this->catalogue();
        $this->actingAsAdmin();
        [$singer, $assignment] = $this->singerWithAlto();
        $account = (int) $singer->user_id;
        $submission = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Accepted, 'payable_cents' => 1250, 'paid_at' => now()]);
        $guide = TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'beta_submission_id' => $submission->id]);
        Storage::disk('private')->put($submission->master_key, 'wav');
        Storage::disk('media')->put($guide->media_key, 'aac');
        BetaFeedback::factory()->create(['beta_singer_id' => $singer->id]);

        $response = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/forget");

        $this->assertMatchesContract($response, 'admin.beta.singers.forget', 200);
        $response->assertJsonPath('data.name', '')->assertJsonPath('data.email', null)->assertJsonPath('data.link', 'revoked')->assertJsonPath('data.paidCents', 1250);
        $this->assertModelMissing($guide);
        Storage::disk('media')->assertMissing($guide->media_key);
        Storage::disk('private')->assertMissing($submission->master_key);
        $this->assertSame(0, BetaFeedback::query()->count());
        $this->assertNotNull($singer->refresh()->forgotten_at);

        // The account stays, without its singer record.
        $this->flushSession();
        $this->actingAs(User::query()->findOrFail($account));
        $this->getJson('/api/beta/me')->assertStatus(403)->assertJsonPath('code', 'not_beta_singer');
    }

    public function test_every_part_must_exist_in_its_template(): void
    {
        $this->catalogue();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/beta/singers', ['name' => 'Sarah', 'parts' => [['templateId' => 'amazing-grace', 'partKey' => 'baritone']]]);

        $this->assertMatchesContract($response, 'admin.beta.singers.create', 422);
        $response->assertJsonPath('errors', ['parts.0.partKey' => [__('validation.part_missing', ['template' => 'Amazing Grace', 'part' => 'baritone'])]]);
    }

    public function test_the_list_shows_consent_and_what_is_owed_and_marking_paid_needs_sudo(): void
    {
        $this->catalogue();
        $this->actingAsAdmin();
        [$singer, $assignment] = $this->singerWithAlto();
        $first = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'status' => SubmissionStatus::Submitted]);
        $second = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'status' => SubmissionStatus::Submitted]);
        Bus::fake([PublishGuide::class]);
        $this->postJson("/api/admin/beta/submissions/{$first->sqid()}/accept")->assertOk();
        $this->postJson("/api/admin/beta/submissions/{$second->sqid()}/accept", ['makeDefault' => false])->assertOk();

        // A part is paid once (two accepted takes of it, a default and an alternate), at today's rate.
        $list = $this->getJson('/api/admin/beta/singers');
        $this->assertMatchesContract($list, 'admin.beta.singers.list', 200);
        $list->assertJsonPath('data.0.owedCents', 1250)->assertJsonPath('data.0.consent.current', true)->assertJsonPath('data.0.assignments.0.status', 'accepted');
        $singer->forceFill(['hourly_rate_cents' => 6000])->save();
        $this->getJson('/api/admin/beta/singers')->assertJsonPath('data.0.owedCents', 1250);

        $paid = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/paid");
        $this->assertMatchesContract($paid, 'admin.beta.singers.paid', 200);
        $paid->assertJsonPath('data.owedCents', 0)->assertJsonPath('data.paidCents', 1250);

        $this->travel(31)->minutes();
        $this->assertMatchesContract($this->postJson("/api/admin/beta/singers/{$singer->sqid()}/paid"), 'admin.beta.singers.paid', 403);
    }

    public function test_a_new_link_stops_the_old_one_and_revoking_stops_both(): void
    {
        $this->actingAsAdmin();
        $singer = BetaSinger::factory()->withToken('old-token')->create();

        $response = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/link", ['expiresAt' => null]);
        $this->assertMatchesContract($response, 'admin.beta.singers.link', 200);
        $this->assertNotSame(hash('sha256', 'old-token'), $singer->refresh()->token_hash);

        $revoked = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/revoke");
        $this->assertMatchesContract($revoked, 'admin.beta.singers.revoke', 200);
        $revoked->assertJsonPath('data.link', 'revoked');
        $this->assertSame(['beta.link_renewed', 'beta.revoked'], AuditLog::query()->orderBy('id')->pluck('action')->all());

        // Once accepted, the singer signs in: there's no link left to replace.
        $accepted = BetaSinger::factory()->accepted()->create();
        $this->assertMatchesContract($this->postJson("/api/admin/beta/singers/{$accepted->sqid()}/link", ['expiresAt' => null]), 'admin.beta.singers.link', 409);
    }

    public function test_updating_a_singer(): void
    {
        $this->actingAsAdmin();
        $singer = BetaSinger::factory()->create();

        $response = $this->patchJson("/api/admin/beta/singers/{$singer->sqid()}", ['note' => 'Lovely alto', 'email' => null]);
        $this->assertMatchesContract($response, 'admin.beta.singers.update', 200);
        $response->assertJsonPath('data.note', 'Lovely alto')->assertJsonPath('data.email', null);

        $this->assertMatchesContract($this->patchJson("/api/admin/beta/singers/{$singer->sqid()}", ['currency' => 'US']), 'admin.beta.singers.update', 422);
    }

    public function test_assigning_and_unassigning_parts(): void
    {
        $this->catalogue();
        $this->actingAsAdmin();
        [$singer, $assignment] = $this->singerWithAlto();

        $created = $this->postJson("/api/admin/beta/singers/{$singer->sqid()}/assignments", ['templateId' => 'amazing-grace', 'partKey' => 'tenor', 'minutes' => 30]);
        $this->assertMatchesContract($created, 'admin.beta.assignments.create', 201);
        $this->assertMatchesContract($this->postJson("/api/admin/beta/singers/{$singer->sqid()}/assignments", ['templateId' => 'amazing-grace', 'partKey' => 'tenor']), 'admin.beta.assignments.create', 422);

        $this->assertMatchesContract($this->deleteJson('/api/admin/beta/assignments/'.$created->json('data.id')), 'admin.beta.assignments.delete', 204);

        BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id]);
        $this->assertMatchesContract($this->deleteJson("/api/admin/beta/assignments/{$assignment->sqid()}"), 'admin.beta.assignments.delete', 409);
        $this->assertModelExists($assignment);
    }

    public function test_singers_and_payments_are_the_admins_not_an_editors(): void
    {
        $this->actingAsEditor();

        $this->assertMatchesContract($this->getJson('/api/admin/beta/singers'), 'admin.beta.singers.list', 403);
        $this->assertSame(0, BetaAssignment::query()->count());
    }
}
