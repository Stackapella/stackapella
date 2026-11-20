<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Enums\AiUploadStatus;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Admin's AI voices (admin.aiVoices.*) and AI guides in its guide list, retired as any guide is removed.
 */
final class AdminAiVoicesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_editors_list_the_voices_with_their_consent_and_live_guides(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create(['name' => 'Alto A', 'based_on' => 'Ada E.']);
        AiVoice::factory()->create(['name' => 'Tenor B', 'based_on' => null]);
        $guide = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        $this->actingAsEditor();

        $list = $this->getJson('/api/admin/ai-voices');
        $this->assertMatchesContract($list, 'admin.aiVoices.list', 200);
        $list->assertJsonPath('data.1.name', 'Alto A')
            ->assertJsonPath('data.1.consent.version', 'ai-voice-1')
            ->assertJsonPath('data.1.credited', false)
            ->assertJsonPath('data.1.status', 'active')
            ->assertJsonPath('data.1.guides.0.id', $guide->sqid())
            ->assertJsonPath('data.1.guides.0.templateId', 'amazing-grace')
            ->assertJsonPath('data.0.consent', null)
            ->assertJsonPath('data.0.basedOn', null);
    }

    public function test_withdrawing_a_voice_retires_its_guides_deletes_its_masters_and_cant_be_repeated(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->consented()->create();
        $guide = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto']);
        Storage::disk('media')->put($guide->media_key, 'aac');
        $upload = AiUpload::factory()->create(['ai_voice_id' => $voice->id, 'template_version_id' => $template->current_version_id, 'status' => AiUploadStatus::Processing, 'master_key' => 'ai/masters/x.wav']);
        Storage::disk('private')->put('ai/masters/x.wav', 'wav');
        $this->actingAsEditor();
        $this->postJson("/api/admin/ai-voices/{$voice->sqid()}/withdraw")->assertForbidden();

        $this->actingAsAdmin();
        $withdrawn = $this->postJson("/api/admin/ai-voices/{$voice->sqid()}/withdraw");
        $this->assertMatchesContract($withdrawn, 'admin.aiVoices.withdraw', 200);
        $withdrawn->assertJsonPath('data.status', 'withdrawn')->assertJsonPath('data.guides', [])->assertJsonPath('meta.retired', 1);

        $this->assertNull(TemplateGuide::withAi()->find($guide->id));
        Storage::disk('media')->assertMissing($guide->media_key);
        Storage::disk('private')->assertMissing('ai/masters/x.wav');
        $this->assertSame('voice_withdrawn', $upload->refresh()->failure);
        $this->assertNotNull($voice->refresh()->withdrawn_at);

        $this->assertMatchesContract($this->postJson("/api/admin/ai-voices/{$voice->sqid()}/withdraw"), 'admin.aiVoices.withdraw', 409);
        $this->assertMatchesContract($this->postJson('/api/admin/ai-voices/zzzzzzzz/withdraw'), 'admin.aiVoices.withdraw', 404);
    }

    public function test_the_guide_list_marks_ai_guides_without_counting_them_and_retire_removes_one(): void
    {
        $template = $this->catalogue();
        $voice = AiVoice::factory()->create(['name' => 'Tenor B']);
        $guide = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        Storage::disk('media')->put($guide->media_key, 'aac');
        $this->actingAsAdmin();

        $list = $this->getJson('/api/admin/beta/guides?template=amazing-grace');
        $this->assertMatchesContract($list, 'admin.beta.guides.list', 200);
        $tenor = collect($list->json('data.0.parts'))->firstWhere('key', 'tenor');
        $this->assertSame('ai', $tenor['guides'][0]['source']);
        $this->assertSame(['id' => $voice->sqid(), 'name' => 'Tenor B'], $tenor['guides'][0]['aiVoice']);
        $this->assertSame('ai', $tenor['guides'][0]['recording']['source']);
        $this->assertFalse($tenor['needs']['voiced'], 'An AI voice does not voice the part.');

        $this->postJson("/api/admin/beta/guides/{$guide->sqid()}/default")->assertOk()->assertJsonPath('data.isDefault', true);

        Notification::fake();
        $this->assertMatchesContract($this->deleteJson("/api/admin/beta/guides/{$guide->sqid()}"), 'admin.beta.guides.delete', 204);
        $this->assertNull(TemplateGuide::withAi()->find($guide->id));
        Storage::disk('media')->assertMissing($guide->media_key);
        Notification::assertNothingSent();
        $this->assertTrue(AuditLog::query()->where('action', 'ai.guide_retired')->exists());
    }

    public function test_an_ai_guide_can_be_reported_where_it_can_be_heard(): void
    {
        $template = $this->catalogue();
        $guide = TemplateGuide::factory()->ai()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'tenor']);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/reports', ['guideId' => $guide->sqid(), 'reason' => 'offensive'])->assertNotFound();
        app(Flags::class)->set('ai_voices', 'on', null, 'Testing AI voices');
        $this->postJson('/api/reports', ['guideId' => $guide->sqid(), 'reason' => 'offensive'])->assertNoContent();
        $this->assertSame(1, Report::query()->where('reportable_type', 'template_guide')->where('reportable_id', $guide->id)->count());
    }
}
