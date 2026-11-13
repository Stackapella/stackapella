<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\SongAudio\SongAudioFiles;
use App\Support\Templates\Catalogue;
use Illuminate\Console\Command;

/** The open songs whose whole-song render is missing or out of date, for the showreel agent to render next. */
final class StaleSongAudioCommand extends Command
{
    protected $signature = 'app:song-audio:stale {--slugs : Only the slugs, one a line}';

    protected $description = 'List the open songs needing a new whole-song render, and why';

    public function handle(Catalogue $catalogue): int
    {
        $stale = SongAudioFiles::stale($catalogue->open()->values());
        // Its version against the song's current one; its voices against the guides published here (run on production for those).
        foreach ($stale as $slug => $why) {
            $this->line($this->option('slugs') ? $slug : "{$slug}  {$why}");
        }
        if (! $this->option('slugs')) {
            $this->info(count($stale).' to render.');
        }

        return self::SUCCESS;
    }
}
