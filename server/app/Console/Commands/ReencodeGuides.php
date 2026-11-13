<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReencodeGuide;
use App\Models\TemplateGuide;
use Illuminate\Console\Command;

/**
 * Every published guide not yet at beta.guide_kbps, queued to be encoded again from its master, in place (ReencodeGuide).
 * Run once after the setting changes; nothing to do while it stays at the guides' bitrate.
 */
final class ReencodeGuides extends Command
{
    protected $signature = 'app:guides:reencode {--dry-run : Only say how many}';

    protected $description = 'Encode the published guides again at the configured bitrate, from their masters';

    public function handle(): int
    {
        $kbps = (int) config('beta.guide_kbps');
        $query = TemplateGuide::query()->whereNotNull('beta_submission_id')
            ->where(fn ($q) => $kbps === 128 ? $q->whereNotNull('kbps')->where('kbps', '!=', 128) : $q->whereNull('kbps')->orWhere('kbps', '!=', $kbps));
        $count = $query->count();

        if (! $this->option('dry-run')) {
            $query->pluck('id')->each(fn (mixed $id) => ReencodeGuide::dispatch((int) $id));
        }
        $this->info($count.' '.($count === 1 ? 'guide' : 'guides').($this->option('dry-run') ? ' to encode again' : ' queued').' at '.$kbps.' kbps.');

        return self::SUCCESS;
    }
}
