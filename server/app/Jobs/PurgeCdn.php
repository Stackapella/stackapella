<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DigitalOcean\CdnPurge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Take files off the CDN's edge, trying again until it works: a shared harmony taken down must
 * stop playing everywhere, not only on the bucket (its copies are cached for a year).
 */
final class PurgeCdn implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 8;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800, 3600];

    /**
     * @param  list<string>  $keys
     */
    public function __construct(public readonly array $keys) {}

    public function handle(CdnPurge $purge): void
    {
        $purge->forgetOrFail($this->keys);
    }
}
