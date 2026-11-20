<?php

declare(strict_types=1);

namespace App\Actions\AiVoices;

use App\Enums\AiUploadStatus;
use App\Jobs\PublishAiGuide;
use App\Models\AiUpload;
use App\Support\Storage\ReceivedUploads;
use Illuminate\Support\Facades\Storage;

/**
 * worker.ai.uploads.complete: the PUT is done. The file is copied to a key no link points at and must be exactly the
 * declared size and SHA-256 (ReceivedUploads), then it's checked and published on the `long` queue (PublishAiGuide).
 * Safe to call again: an upload past `uploading` is answered as it is.
 */
final class CompleteAiUpload
{
    public function handle(AiUpload $upload): AiUpload
    {
        if ($upload->status !== AiUploadStatus::Uploading) {
            return $upload;
        }

        $received = app(ReceivedUploads::class)->receive($upload->upload_key, $upload->bytes, $upload->sha256, 'ai/masters');

        // Another complete is checking it this moment: as it is.
        if ($received === null) {
            return $upload->refresh();
        }

        // Claimed in one statement: two completes at once publish once.
        $claimed = AiUpload::query()->whereKey($upload->id)->where('status', AiUploadStatus::Uploading->value)
            ->update(['status' => AiUploadStatus::Processing->value, 'master_key' => $received]);

        if ($claimed === 1) {
            PublishAiGuide::dispatch($upload->id);
        } else {
            Storage::disk('private')->delete($received);
        }

        return $upload->refresh();
    }
}
