<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AiUploadStatus;
use App\Models\AiUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * The AI masters nobody needs (docs/ai-voices-build-plan.md): an upload never completed goes after an hour (its link
 * lasts 30 minutes), marked `expired`; a failed upload's master goes, and so does the master of a guide since retired
 * (a real voice took the part, or a new render replaced it). A live AI guide's master is kept, as a beta take's is, so
 * it can be encoded again. Runs hourly.
 */
final class PruneAiUploads extends Command
{
    protected $signature = 'app:ai-uploads:prune';

    protected $description = 'Expire AI uploads never completed after an hour, and delete the masters of failed uploads and retired AI guides';

    public function handle(): int
    {
        $expired = 0;
        AiUpload::query()->where('status', AiUploadStatus::Uploading->value)->where('created_at', '<', now()->subHour())
            ->chunkById(200, function ($uploads) use (&$expired): void {
                foreach ($uploads as $upload) {
                    /** @var AiUpload $upload */
                    Storage::disk('private')->delete($upload->upload_key);
                    $upload->forceFill(['status' => AiUploadStatus::Failed, 'failure' => 'expired'])->saveQuietly();
                    $expired++;
                }
            });

        $deleted = 0;
        AiUpload::query()->whereNotNull('master_key')
            ->where(fn ($query) => $query->where('status', AiUploadStatus::Failed->value)
                ->orWhere(fn ($retired) => $retired->where('status', AiUploadStatus::Published->value)->whereNull('template_guide_id')))
            ->chunkById(200, function ($uploads) use (&$deleted): void {
                foreach ($uploads as $upload) {
                    /** @var AiUpload $upload */
                    Storage::disk('private')->delete((string) $upload->master_key);
                    $upload->forceFill(['master_key' => null])->saveQuietly();
                    $deleted++;
                }
            });

        $this->info("Expired {$expired} unfinished AI uploads and deleted {$deleted} masters.");

        return self::SUCCESS;
    }
}
