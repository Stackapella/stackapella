<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Jobs\ProcessTake;
use App\Models\Take;
use App\Models\User;
use App\Support\Storage\ReceivedUploads;
use App\Support\Sync\SyncSeq;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * takes.complete: the stored WAV must be exactly the declared size and SHA-256 (a wrong one is
 * deleted at once), then it's processed on the `long` queue. Safe to call again.
 */
final class CompleteTake
{
    public function __construct(private readonly Owned $owned, private readonly SyncSeq $seq) {}

    public function handle(User $user, string $uuid): Take
    {
        $take = $this->owned->take($user, $uuid);

        // A singer who has left (or was removed) can't finish an upload into the project.
        if ($take->user_id !== $user->id && Owned::role($user, $take->loadMissing('project')->project) === null) {
            throw new Refusal(404, 'not_found');
        }

        if ($take->status !== Take::UPLOADING) {
            return $take;
        }

        $received = app(ReceivedUploads::class)->receive($take->upload_key, $take->upload_bytes, $take->sha256);

        // Another complete is checking it this moment: as it is.
        if ($received === null) {
            return $take->refresh();
        }

        // Claimed in one statement: two completes at once process it once.
        $claimed = DB::transaction(fn (): int => Take::query()->whereKey($take->id)->where('status', Take::UPLOADING)->update(['status' => Take::PROCESSING, 'upload_key' => $received, 'seq' => $this->seq->next($take->user_id)]));

        if ($claimed === 1) {
            ProcessTake::dispatch($take->id);
        } else {
            Storage::disk('private')->delete($received);
        }

        return $take->refresh();
    }
}
