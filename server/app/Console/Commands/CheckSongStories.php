<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Pages\SongStories;
use Illuminate\Console\Command;

/**
 * The songs' stories' content check (docs/songs/story-guide.md): every story, English and each translation, against
 * the guide's rules (its sections in order, every claim's source there and cited, enough sources, questions, no em
 * dash, no address outside Sources, a short description, a translation's sources the same). Fails on any problem.
 */
final class CheckSongStories extends Command
{
    protected $signature = 'app:stories:check {song? : One song\'s story, like silent-night}';

    protected $description = 'Check the songs\' stories against the story guide';

    public function handle(): int
    {
        $song = $this->argument('song');
        $slugs = is_string($song) ? [$song] : SongStories::slugs();
        $problems = [];
        foreach ($slugs as $slug) {
            $problems = [...$problems, ...SongStories::check($slug)];
        }

        foreach ($problems as $problem) {
            $this->line($problem);
        }
        $this->info(count($slugs).' '.(count($slugs) === 1 ? 'story' : 'stories').' checked, '.count($problems).' '.(count($problems) === 1 ? 'problem' : 'problems').'.');

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
