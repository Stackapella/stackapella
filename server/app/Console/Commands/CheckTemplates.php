<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Templates\ArrangementCheck;
use Illuminate\Console\Command;

/**
 * The arranger's checks on every official template (App\Support\Templates\ArrangementCheck;
 * docs/arrangements-final-review.md): the hard errors, then the advice for the musician, with a
 * count of each so a fix shows as a smaller number. Reads the files the catalogue is synced from.
 */
final class CheckTemplates extends Command
{
    protected $signature = 'app:templates:check {song? : One song\'s id, like amazing-grace} {--advice : List every piece of advice, not only the counts}';

    protected $description = 'Check the official templates\' arrangements: timing, ranges, leaps, parallels, chords, thirds and words';

    public function handle(ArrangementCheck $check): int
    {
        $only = $this->argument('song');
        $rows = [];
        $hard = 0;
        $advice = 0;

        foreach (self::templates() as $id => $template) {
            if (is_string($only) && $only !== $id) {
                continue;
            }

            $found = $check->check($template);
            $hard += count($found['hard']);
            $advice += count($found['advice']);
            $kinds = array_count_values(array_map(static fn (string $line): string => strstr($line, ':', true) ?: 'other', $found['advice']));
            $rows[] = [$id, count($found['hard']), count($found['advice']), implode(', ', array_map(static fn (string $k, int $n): string => "{$k} {$n}", array_keys($kinds), $kinds))];

            foreach ($found['hard'] as $line) {
                $this->error("{$id}: {$line}");
            }

            if ($this->option('advice') || is_string($only)) {
                foreach ($found['advice'] as $line) {
                    $this->line("  {$id}: {$line}");
                }
            }
        }

        if ($rows === []) {
            $this->error(is_string($only) ? "No official template \"{$only}\"." : 'No official templates found.');

            return self::FAILURE;
        }

        $this->table(['Song', 'Hard', 'Advice', 'Advice by kind'], $rows);
        $this->line("{$hard} hard errors, {$advice} pieces of advice across ".count($rows).' songs.');

        return $hard === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Every official template, by id, from the folders the catalogue is synced from.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function templates(): array
    {
        $found = [];

        foreach ((array) config('templates.folders') as $folder) {
            foreach (glob(rtrim((string) $folder, '/').'/*.json') ?: [] as $path) {
                $template = json_decode((string) file_get_contents($path), true);

                if (basename($path) !== 'index.json' && is_array($template) && isset($template['id'], $template['parts'])) {
                    $found[(string) $template['id']] = $template;
                }
            }
        }

        ksort($found);

        return $found;
    }
}
