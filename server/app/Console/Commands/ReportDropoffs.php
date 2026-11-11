<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Growth\Dropoffs;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The weekly drop-off report (App\Actions\Growth\Dropoffs): the last seven whole days, kept as that week's snapshot.
 * Saturdays just after midnight (the week to Friday), and again by hand for any week (--week-end=YYYY-MM-DD).
 */
final class ReportDropoffs extends Command
{
    protected $signature = 'app:dropoffs {--week-end= : The last day of the week to report (default: yesterday)}';

    protected $description = 'Work out the week\'s drop-off report: where singers stop, path by path';

    public function handle(Dropoffs $dropoffs): int
    {
        $given = $this->option('week-end');
        $end = is_string($given) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $given) === 1 ? Carbon::parse($given) : Dropoffs::lastWeekEnd();
        $report = $dropoffs->snapshot($end);
        $worst = (array) ($report->data['worst'] ?? []);

        $this->info('Drop-offs for the week to '.$end->toDateString().': '.count((array) ($report->data['paths'] ?? [])).' paths, '.count($worst).' drops ranked.');
        foreach ($worst as $step) {
            $this->line('  '.$step['pathLabel'].': '.$step['label'].', '.$step['lost'].' lost');
        }

        return self::SUCCESS;
    }
}
