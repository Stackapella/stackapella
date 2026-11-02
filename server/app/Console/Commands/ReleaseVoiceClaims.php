<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Voices\LendVoice;
use Illuminate\Console\Command;

/**
 * Lend your voice: claims that ran out with nothing sent are let go, back on the Wanted list.
 */
final class ReleaseVoiceClaims extends Command
{
    protected $signature = 'app:voices:release';

    protected $description = 'Let go of Lend your voice claims that ran out with nothing sent';

    public function handle(): int
    {
        $this->info('Released '.LendVoice::releaseExpired().' claims.');

        return self::SUCCESS;
    }
}
