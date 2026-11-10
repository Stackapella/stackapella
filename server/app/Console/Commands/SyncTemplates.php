<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Ops\SyncProblems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Load the official templates into the catalogue. Runs on every deploy, after the migrations.
 *
 * A song it can't load (a broken file, notes changed under a published version) is skipped and
 * the rest still load: the problem is printed, logged as an error and kept (SyncProblems) for
 * Readiness, admin's overview and /up, and the deploy goes on with that song's live version.
 * It fails only when not one song loads. --strict fails on any problem, for tests and checks.
 */
final class SyncTemplates extends Command
{
    protected $signature = 'app:templates:sync {--strict : Fail on any song that can\'t be loaded, not only when none can}';

    protected $description = 'Load the official templates (web/templates, database/templates) into the catalogue';

    public function handle(SyncOfficialTemplates $sync): int
    {
        $report = $sync->handle();

        foreach ($report['added'] as $added) {
            $this->info("Added {$added}");
        }

        foreach ($report['opened'] as $opened) {
            $this->info("Opened {$opened}: reviewed now, no longer hidden");
        }

        $this->line(count($report['unchanged']).' unchanged.');

        // No song files at all: the courses and songs live in their own repo, fetched first.
        if ($report['added'] === [] && $report['opened'] === [] && $report['unchanged'] === [] && $report['problems'] === []) {
            $this->warn('No songs found. Fetch them first: sh server/tool/content.sh fetch (from the repo root), then run this again.');
        }

        foreach ($report['carried'] as $carried) {
            $this->info("Carried over {$carried}");
        }

        foreach ($report['withdrawn'] as $withdrawn) {
            $this->warn("Withdrawn from the library (its file is gone; its projects keep it): {$withdrawn}");
        }

        foreach ($report['problems'] as $problem) {
            $this->error($problem);
        }

        SyncProblems::save($report['problems']);

        if ($report['problems'] === []) {
            return self::SUCCESS;
        }

        Log::error('Songs not synced: their live versions stay until each is fixed', ['problems' => $report['problems']]);

        if ($this->option('strict') || ($report['added'] === [] && $report['opened'] === [] && $report['unchanged'] === [])) {
            return self::FAILURE;
        }

        $this->warn(count($report['problems']).' not synced; everything else is. Each keeps its live version until fixed (Admin, Readiness).');

        return self::SUCCESS;
    }
}
