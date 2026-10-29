<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Templates\SyncOfficialTemplates;
use Illuminate\Console\Command;

/**
 * Load the official templates into the catalogue. Runs on every deploy, after the migrations.
 */
final class SyncTemplates extends Command
{
    protected $signature = 'app:templates:sync';

    protected $description = 'Load the official templates (web/templates, database/templates) into the catalogue';

    public function handle(SyncOfficialTemplates $sync): int
    {
        $report = $sync->handle();

        foreach ($report['added'] as $added) {
            $this->info("Added {$added}");
        }

        $this->line(count($report['unchanged']).' unchanged.');

        foreach ($report['carried'] as $carried) {
            $this->info("Carried over {$carried}");
        }

        foreach ($report['withdrawn'] as $withdrawn) {
            $this->warn("Withdrawn from the library (its file is gone; its projects keep it): {$withdrawn}");
        }

        foreach ($report['problems'] as $problem) {
            $this->error($problem);
        }

        return $report['problems'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
