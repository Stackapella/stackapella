<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Storage\UploadSweeper;
use Illuminate\Console\Command;

/**
 * Presigned upload keys swept (UploadSweeper): anything bigger than declared deleted at once, with every version, and
 * every version left at a link's key once it's done. Every five minutes.
 */
final class SweepUploads extends Command
{
    protected $signature = 'app:uploads:sweep';

    protected $description = 'Delete uploads bigger than declared, and every version left at done upload links';

    public function handle(UploadSweeper $sweeper): int
    {
        ['oversized' => $oversized, 'swept' => $swept] = $sweeper->sweep();
        $this->info("Deleted {$oversized} oversized uploads and swept {$swept} done links.");

        return self::SUCCESS;
    }
}
