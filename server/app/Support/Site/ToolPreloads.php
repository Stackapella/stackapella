<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Support\Facades\Cache;

/**
 * Each tool module's whole static import graph (web/js/public/tools/preloads.json, written by
 * web/tool/tool_preloads.py), so a tool's page can name every module in its head as a
 * <link rel="modulepreload"> and a slow phone fetches them at once, not one import at a time.
 * Only our own /js/ modules are ever given; [] when the file or the module isn't there. Cached by
 * the file's own time, so a deploy's new file is read at once.
 */
final class ToolPreloads
{
    public const FILE = '/js/public/tools/preloads.json';

    /** More than a tool needs; a runaway file never fills a page's head. */
    private const MOST = 60;

    /**
     * @return list<string>
     */
    public static function for(string $module): array
    {
        $graphs = self::all();

        return $graphs[$module] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function all(): array
    {
        $path = rtrim((string) config('app.web_path'), '/').self::FILE;
        $time = is_file($path) ? (int) filemtime($path) : 0;

        if ($time === 0) {
            return [];
        }

        /** @var array<string, list<string>> */
        return Cache::remember('tools.preloads.'.$time, 3600, static function () use ($path): array {
            $data = json_decode((string) file_get_contents($path), true);
            $graphs = [];

            foreach (is_array($data) ? $data : [] as $module => $files) {
                if (! is_string($module) || ! self::ours($module) || ! is_array($files)) {
                    continue;
                }
                $graphs[$module] = array_slice(array_values(array_unique(array_filter($files, static fn (mixed $file): bool => is_string($file) && self::ours($file)))), 0, self::MOST);
            }

            return $graphs;
        });
    }

    /** One of our own modules: /js/..., a .js file, no way out of the folder. */
    private static function ours(string $file): bool
    {
        return preg_match('#^/js/[A-Za-z0-9/_.-]+\.js$#', $file) === 1 && ! str_contains($file, '..');
    }
}
