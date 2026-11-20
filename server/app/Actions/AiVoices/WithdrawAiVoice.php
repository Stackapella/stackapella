<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Enums\AiUploadStatus;
use App\Exceptions\Refusal;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\AuditLog;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\AiVoices\AiGuides;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The singer took back their consent, or Hillel took the voice down (admin.aiVoices.withdraw): every AI guide it sang
 * is retired at once and purged from the CDN, its masters and unfinished uploads are deleted, and the worker refuses it
 * from then on. Can't be undone: a voice coming back is a new voice with a new consent.
 */
final class WithdrawAiVoice
{
    /**
     * @return int how many guides were retired
     */
    public function handle(AiVoice $voice, ?User $actor, ?string $ip = null): int
    {
        /** @var array{retired: int, files: list<string>} $done */
        $done = DB::transaction(function () use ($voice, $actor, $ip): array {
            $locked = AiVoice::query()->whereKey($voice->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new Refusal(404, 'not_found');
            }

            if (! $locked->isActive()) {
                throw new Refusal(409, 'already_withdrawn');
            }

            // The voice, then its uploads, then its guides: PublishAiGuide's order, so the two never deadlock.
            $uploads = AiUpload::query()->where('ai_voice_id', $locked->id)->lockForUpdate()->get();
            $locked->forceFill(['withdrawn_at' => now()])->save();
            $guides = TemplateGuide::withAi()->where('ai_voice_id', $locked->id)->lockForUpdate()->get();
            AiGuides::retire($guides, $actor, 'withdrawn');

            $files = $uploads->flatMap(static fn (AiUpload $upload): array => array_values(array_filter([$upload->upload_key, $upload->master_key])))->values()->all();
            AiUpload::query()->where('ai_voice_id', $locked->id)->whereIn('status', [AiUploadStatus::Uploading->value, AiUploadStatus::Processing->value])
                ->update(['status' => AiUploadStatus::Failed->value, 'failure' => 'voice_withdrawn']);
            AiUpload::query()->where('ai_voice_id', $locked->id)->update(['master_key' => null]);

            AuditLog::record('ai.voice_withdrawn', $locked, $actor, after: ['guides' => $guides->count(), 'uploads' => $uploads->count()], ip: $ip);

            return ['retired' => $guides->count(), 'files' => $files];
        });

        // Its masters (private): nothing it sang is kept.
        if ($done['files'] !== []) {
            Storage::disk('private')->delete($done['files']);
        }

        $voice->refresh();

        return $done['retired'];
    }
}
