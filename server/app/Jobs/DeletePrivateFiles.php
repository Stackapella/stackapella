<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Files in `private` whose rows are already gone (a deleted account's takes), deleted in the
 * background so the request doesn't wait on the storage.
 */
final class DeletePrivateFiles implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /**
     * @param  list<string>  $keys
     */
    public function __construct(public readonly array $keys) {}

    public function handle(): void
    {
        Storage::disk('private')->delete($this->keys);
    }
}
