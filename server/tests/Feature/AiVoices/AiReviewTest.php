<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Enums\AiUploadStatus;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Admin\SecondFactor;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Phase 1c: Hillel reviews new AI guides before they reach anyone, and the runner reads the whole catalogue's to-do list.
 */
final class AiReviewTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    /**
     * A part's render published and waiting for review, as PublishAiGuide leaves it.
     */
    private function published(Template $template, string $part, AiVoice $voice): AiUpload
    {
        $guide = TemplateGuide::factory()->ai($voice)->pendingReview()->create(['template_version_id' => $template->current_version_id, 'part_key' => $part]);
        Storage::disk('media')->put($guide->media_key, 'aac');

        return AiUpload::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => $part, 'ai_voice_id' => $voice->id, 'status' => AiUploadStatus::Published, 'template_guide_id' => $guide->id, 'review' => 'pending']);
    }

    private function catalogueAlto(): mixed
    {
        return collect(collect($this->getJson('/api/templates')->json('data'))->firstWhere('id', 'amazing-grace')['parts'])->firstWhere('key', 'alto')['guide'];
    }

    public function test_a_new_ai_guide_waits_for_approval_then_reaches_the_app(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $alto = $this->published($template, 'alto', $voice);
        $tenor = $this->published($template, 'tenor', $voice);
        app(Flags::class)->set('ai_voices', 'on', null, 'Testing');

        $this->assertNull($this->catalogueAlto(), 'Pending: not in the app.');

        $this->actingAsEditor();
        $list = $this->getJson('/api/admin/ai-guides');
        $this->assertMatchesContract($list, 'admin.aiGuides.list', 200);
        $list->assertJsonPath('meta.counts.pending', 2)->assertJsonPath('data.0.templateId', 'amazing-grace')->assertJsonCount(2, 'data.0.parts');
        $this->postJson('/api/admin/ai-guides/approve', ['ids' => [$alto->sqid()]])->assertForbidden();

        $admin = $this->actingAsAdmin();
        $this->assertMatchesContract($this->postJson('/api/admin/ai-guides/approve', []), 'admin.aiGuides.approve', 422);
        $approved = $this->postJson('/api/admin/ai-guides/approve', ['ids' => [$alto->sqid()]]);
        $this->assertMatchesContract($approved, 'admin.aiGuides.approve', 200);
        $approved->assertJsonPath('data.approved', 1);
        // A song's button sends the parts the reviewer saw; one already decided is skipped.
        $this->postJson('/api/admin/ai-guides/approve', ['ids' => [$alto->sqid(), $tenor->sqid()]])->assertJsonPath('data.approved', 1);
        $this->postJson('/api/admin/ai-guides/approve', ['templateId' => 'amazing-grace'])->assertUnprocessable();
        $this->assertSame('approved', $tenor->refresh()->review);
        $this->getJson('/api/admin/ai-guides?status=approved')->assertJsonPath('meta.counts.approved', 2)->assertJsonPath('data.0.parts.0.reviewedBy.id', $admin->sqid());

        $this->actingAs(User::factory()->create());
        $this->assertSame('ai', $this->catalogueAlto()['source']);
    }

    public function test_a_rejected_part_is_retired_and_back_on_the_runners_list_with_the_note(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $alto = $this->published($template, 'alto', $voice);
        $guideKey = (string) TemplateGuide::withAi()->whereKey($alto->template_guide_id)->value('media_key');
        $this->actingAsAdmin();

        // Rejecting deletes files: in sudo, as withdrawing is.
        $this->withSession([SecondFactor::SESSION_PASSED_AT => now()->subDay()->getTimestamp()])->postJson('/api/admin/ai-guides/reject', ['ids' => [$alto->sqid()], 'note' => 'x'])->assertForbidden()->assertJsonPath('code', 'sudo_required');
        $this->withSession([SecondFactor::SESSION_PASSED_AT => now()->getTimestamp()]);
        $this->assertMatchesContract($this->postJson('/api/admin/ai-guides/reject', ['ids' => [$alto->sqid()]]), 'admin.aiGuides.reject', 422);
        $rejected = $this->postJson('/api/admin/ai-guides/reject', ['ids' => [$alto->sqid()], 'note' => 'Flat on the last line.']);
        $this->assertMatchesContract($rejected, 'admin.aiGuides.reject', 200);
        $rejected->assertJsonPath('data.rejected', 1);
        Storage::disk('media')->assertMissing($guideKey);
        $this->assertSame(0, TemplateGuide::withAi()->where('source', 'ai')->count());
        $this->postJson('/api/admin/ai-guides/reject', ['ids' => [$alto->sqid()], 'note' => 'again'])->assertJsonPath('data.rejected', 0);

        $list = $this->getJson('/api/admin/ai-guides?status=rejected');
        $this->assertMatchesContract($list, 'admin.aiGuides.list', 200);
        $list->assertJsonPath('data.0.parts.0.recording', null)->assertJsonPath('data.0.parts.0.note', 'Flat on the last line.');

        config(['services.ai_worker.token' => self::TOKEN]);
        $catalogue = $this->json('GET', '/api/worker/ai/catalogue', [], ['Authorization' => 'Bearer '.self::TOKEN]);
        $this->assertMatchesContract($catalogue, 'worker.ai.catalogue', 200);
        $grace = collect($catalogue->json('data'))->firstWhere('template', 'amazing-grace');
        $part = collect($grace['parts'])->firstWhere('key', 'alto');
        $this->assertTrue($part['todo']);
        $this->assertSame('Flat on the last line.', $part['rejected']['note']);

        // A later render that failed doesn't hide the note.
        AiUpload::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'ai_voice_id' => $voice->id, 'status' => AiUploadStatus::Failed, 'failure' => 'voice_withdrawn']);
        $again = collect(collect($this->json('GET', '/api/worker/ai/catalogue', [], ['Authorization' => 'Bearer '.self::TOKEN])->json('data'))->firstWhere('template', 'amazing-grace')['parts'])->firstWhere('key', 'alto');
        $this->assertSame('Flat on the last line.', $again['rejected']['note']);
        $this->assertGreaterThan(10, $part['seconds']);
        $this->assertLessThan($part['range']['high'], $part['range']['low']);
    }

    public function test_the_catalogue_list_marks_exactly_what_to_render(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'bass']);
        $this->published($template, 'alto', $voice);
        AiUpload::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor', 'ai_voice_id' => $voice->id, 'status' => AiUploadStatus::Processing]);
        config(['services.ai_worker.token' => self::TOKEN]);

        $catalogue = $this->json('GET', '/api/worker/ai/catalogue', [], ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();
        $parts = collect(collect($catalogue->json('data'))->firstWhere('template', 'amazing-grace')['parts'])->keyBy('key');

        $this->assertSame(['soprano' => true, 'alto' => false, 'tenor' => false, 'bass' => false], $parts->map(static fn (array $p): bool => $p['todo'])->all());
        $this->assertSame('pending', $parts['alto']['aiGuide']['review']);
        $this->assertSame('processing', $parts['tenor']['pending']['status']);
        $this->assertTrue($parts['bass']['hasRealGuide']);
        $this->assertSame([true], array_column($catalogue->json('meta.voices'), 'licensed'));
    }
}
