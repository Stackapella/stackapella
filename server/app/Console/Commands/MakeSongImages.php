<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Pages\SongMedia;
use App\Support\Pages\SongStories;
use Illuminate\Console\Command;

/**
 * Makes a song story's pictures' WebP sizes (App\Support\Pages\SongMedia: 480, 960 and 1440 wide, never upscaled)
 * beside their originals, on a checkout of the content, to commit with them: production only serves the files.
 */
final class MakeSongImages extends Command
{
    protected $signature = 'app:stories:images {song? : One song, like silent-night (every song with pictures when left out)}';

    protected $description = 'Make the WebP sizes of the songs\' story pictures';

    public function handle(): int
    {
        $song = $this->argument('song');
        $slugs = is_string($song) ? [$song] : array_values(array_filter(SongStories::slugs(), static fn (string $slug): bool => SongMedia::records($slug) !== []));
        $written = 0;
        foreach ($slugs as $slug) {
            $written += count(SongMedia::make($slug));
        }
        $this->info($written.' WebP files written for '.count($slugs).' '.(count($slugs) === 1 ? 'song' : 'songs').'.');

        return self::SUCCESS;
    }
}
