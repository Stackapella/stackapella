<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Templates\VersionChanges;
use Illuminate\Console\Command;

/**
 * Refuses a content pin that production's sync would refuse: a song whose notes changed under the
 * same version number (server/tool/content-bump.sh runs it on the pinned and the new content).
 */
final class CheckTemplateVersions extends Command
{
    protected $signature = 'app:templates:versions {old : The pinned content tree} {new : The content tree about to be pinned}';

    protected $description = 'Check that every song whose notes changed between two content trees has a new version number';

    public function handle(VersionChanges $changes): int
    {
        $old = (string) $this->argument('old');
        $new = (string) $this->argument('new');

        foreach ([$old, $new] as $root) {
            if (! is_dir($root)) {
                $this->error("{$root} isn't a folder.");

                return self::FAILURE;
            }
        }

        $problems = $changes->handle($old, $new);

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->info('Every changed song has a new version.');

        return self::SUCCESS;
    }
}
