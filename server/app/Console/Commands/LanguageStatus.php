<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Languages\Languages;
use App\Support\Languages\TranslationMaps;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Translation\FileLoader;

/**
 * Where a language's server words stand (docs/languages-plan.md; the courses' `tools/i18n.py status`, for lang/):
 * for each group, the English sentences with no translation yet (new, or changed since it was translated: their
 * English shows), and the map's entries whose English is gone (stale, never shown, safe to delete). A translation
 * that drops a placeholder or a plural is counted as missing too, as it's never used. --list names them.
 */
final class LanguageStatus extends Command
{
    protected $signature = 'app:languages:status {language : es or de} {--list : name every sentence still to translate}';

    protected $description = "What's left to translate in the server's words for a language, and what's stale";

    public function handle(): int
    {
        $language = (string) $this->argument('language');

        if ($language === 'en' || ! in_array($language, Languages::ALL, true)) {
            $this->error('A language other than English: '.implode(', ', array_diff(Languages::ALL, ['en'])).'.');

            return self::FAILURE;
        }

        $english = new FileLoader(app('files'), lang_path());
        $rows = [];
        $missingTotal = 0;
        $staleTotal = 0;
        $refusedTotal = 0;

        foreach (File::files(lang_path('en')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $group = $file->getFilenameWithoutExtension();
            $lines = $english->load('en', $group);
            $sentences = self::sentences($lines);
            $map = TranslationMaps::map(lang_path(), $language, $group);
            $missing = array_values(array_filter($sentences, static fn (string $s): bool => ! isset($map[$s]) || ! TranslationMaps::fits($s, $map[$s])));
            // In the map but never used: a placeholder (:name) or a plural's | lost or added, or empty.
            $refused = array_values(array_filter($sentences, static fn (string $s): bool => isset($map[$s]) && ! TranslationMaps::fits($s, $map[$s])));
            $refusedTotal += count($refused);
            // A sentence's English gone, or a key's own translation whose key now says something else.
            $stale = array_values(array_filter(array_map('strval', array_keys($map)), static function (string $key) use ($sentences, $lines): bool {
                [$path, $text] = TranslationMaps::entry($key);

                return $path === null ? ! in_array($text, $sentences, true) : data_get($lines, $path) !== $text;
            }));
            $missingTotal += count($missing);
            $staleTotal += count($stale);
            $rows[] = [$group, count($sentences), count($sentences) - count($missing), count($missing), count($refused), count($stale)];

            foreach ($refused as $sentence) {
                $this->warn("{$group}: refused (its placeholders or plural forms differ from the English): ".mb_strimwidth(str_replace("\n", ' ', $sentence), 0, 140, '...'));
            }

            if ($this->option('list')) {
                foreach ($missing as $sentence) {
                    $this->line("{$group}: ".mb_strimwidth(str_replace("\n", ' ', $sentence), 0, 140, '...'));
                }
            }
        }

        $this->table(['Group', 'Sentences', 'Translated', 'To translate', 'Refused', 'Stale'], $rows);
        $this->guides($language);
        $this->info("{$language}: {$missingTotal} to translate ({$refusedTotal} of them refused), {$staleTotal} stale.");

        return self::SUCCESS;
    }

    /**
     * The guides, translated as whole files (resources/guides/<lang>/<slug>.md): each names the English it was made
     * from as `translatedFrom`, the first 12 hex of the English file's sha256, so a guide whose English changed since
     * is stale (file dates can't tell: a checkout resets them).
     */
    private function guides(string $language): void
    {
        $root = (string) config('content.guides');
        $missing = [];
        $stale = [];
        $all = glob($root.'/en/*.md') ?: [];

        foreach ($all as $english) {
            $slug = basename($english, '.md');
            $translated = $root.'/'.$language.'/'.$slug.'.md';

            if (! is_file($translated)) {
                $missing[] = $slug;

                continue;
            }

            preg_match('/^translatedFrom:\s*([a-f0-9]{12})\s*$/m', (string) file_get_contents($translated), $from);

            if (($from[1] ?? null) !== substr((string) hash_file('sha256', $english), 0, 12)) {
                $stale[] = $slug;
            }
        }

        $this->info('Guides: '.(count($all) - count($missing)).' of '.count($all).' translated, '.count($stale).' stale (their English changed since).');

        if ($this->option('list')) {
            foreach ($missing as $slug) {
                $this->line("guides: {$slug} (not translated)");
            }
        }

        foreach ($stale as $slug) {
            $this->warn("guides: {$slug} is stale: translate it again from the English, and set translatedFrom to its new hash");
        }
    }

    /**
     * Every English sentence in a group, once each.
     *
     * @param  array<array-key, mixed>  $lines
     * @return list<string>
     */
    private static function sentences(array $lines): array
    {
        $out = [];
        array_walk_recursive($lines, static function (mixed $value) use (&$out): void {
            if (is_string($value) && trim($value) !== '') {
                $out[$value] = true;
            }
        });

        return array_map('strval', array_keys($out));
    }
}
