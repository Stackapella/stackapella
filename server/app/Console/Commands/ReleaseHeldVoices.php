<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Voices\ShareVoice;
use Illuminate\Console\Command;

/**
 * Be the voice: shared takes held for an unconfirmed email, sent to processing once the account has confirmed it, by
 * whichever way it was confirmed. Every few minutes; nothing to do while lend_voice is off (nothing is held).
 */
final class ReleaseHeldVoices extends Command
{
    protected $signature = 'app:voices:release-held';

    protected $description = 'Send shared takes on once their accounts have confirmed their email';

    public function handle(): int
    {
        $this->info(ShareVoice::releaseHeld().' held takes sent on.');

        return self::SUCCESS;
    }
}
