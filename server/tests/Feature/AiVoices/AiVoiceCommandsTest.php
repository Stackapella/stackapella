<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Enums\AiUploadStatus;
use App\Models\AiUpload;
use App\Models\AiVoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Adding a voice, recording its consent, and the hourly prune of AI masters nobody needs.
 */
final class AiVoiceCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_voice_is_added_and_its_consent_recorded(): void
    {
        $this->artisan('app:ai-voice:add', ['name' => 'Alto A', '--based-on' => 'Ada E.'])->assertSuccessful();
        $voice = AiVoice::query()->firstOrFail();
        $this->assertSame('Ada E.', $voice->based_on);
        $this->assertFalse($voice->isConsented());

        $this->artisan('app:ai-voice:consent', ['voice' => $voice->sqid(), '--date' => now()->addDay()->toDateString(), '--consent-version' => 'ai-voice-1'])->assertFailed();
        $this->artisan('app:ai-voice:consent', ['voice' => $voice->sqid(), '--date' => '2026-11-19', '--consent-version' => 'ai-voice-1', '--paid' => true, '--credited' => true])->assertSuccessful();
        $voice->refresh();
        $this->assertTrue($voice->isConsented());
        $this->assertSame('Ada E.', $voice->labelName());
        $this->assertTrue($voice->payment_noted);

        $voice->forceFill(['withdrawn_at' => now()])->save();
        $this->artisan('app:ai-voice:consent', ['voice' => $voice->sqid(), '--date' => '2026-11-19', '--consent-version' => 'ai-voice-1'])->assertFailed();
    }

    public function test_unfinished_uploads_expire_and_unneeded_masters_go(): void
    {
        $stale = AiUpload::factory()->create(['created_at' => now()->subHours(2)]);
        Storage::disk('private')->put($stale->upload_key, 'wav');
        $fresh = AiUpload::factory()->create();
        $failed = AiUpload::factory()->create(['status' => AiUploadStatus::Failed, 'failure' => 'real_voice_exists', 'master_key' => 'ai/masters/f.wav']);
        Storage::disk('private')->put('ai/masters/f.wav', 'wav');

        $this->artisan('app:ai-uploads:prune')->assertSuccessful();

        $this->assertSame('expired', $stale->refresh()->failure);
        Storage::disk('private')->assertMissing($stale->upload_key);
        $this->assertSame(AiUploadStatus::Uploading, $fresh->refresh()->status);
        $this->assertNull($failed->refresh()->master_key);
        Storage::disk('private')->assertMissing('ai/masters/f.wav');
    }
}
