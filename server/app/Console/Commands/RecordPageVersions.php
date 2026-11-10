<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PageVersion;
use App\Support\Http\Hosts;
use App\Support\Site\SitemapPaths;
use App\Support\Trust\Limits;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The sitemap's honest lastmod (2026-11-02): each listed page (but songs' and ideas', see
 * SitemapPaths::hashed) is rendered as a visitor gets it, and only what they read is hashed: the
 * title, the meta description and the text of <main> (the body's when there's none), tags and their
 * attributes left out, so a token, an asset's version or a deploy alone never counts. A page whose
 * words differ from last time is dated now; a new page starts now; a page gone from the sitemap is
 * forgotten. Daily, and within minutes of each deploy: the scheduler asks every five minutes with --if-new-build,
 * which reads the pages only when this build has none dated yet (the deploy's own job can't be relied on to run it:
 * its command lives in the host's console). A page that won't render (or throws) is skipped and logged, never a
 * failure (it runs in the deploy); the summary names each skipped page with its status, and the last run's
 * summary is kept (LAST) for the growth pull's meta, as no agent reads the deploy's log. It is saved as the run
 * starts and as it goes (`state` started, then finished), so a run that dies part way (out of memory, killed)
 * still says how far it got, the page it was on and, for a fatal PHP error, the error (`state` died).
 */
final class RecordPageVersions extends Command
{
    protected $signature = 'app:sitemap:dates {--if-new-build : Only when no run has started on this build yet}';

    protected $description = 'Date each sitemap page by when its words last changed';

    /** The last run's summary (the growth pull's meta.sitemapDates). */
    public const LAST = 'sitemap-dates:last';

    /** Skipped pages named in the summary, at most. */
    private const NAMED = 10;

    /** The summary is saved again after this many pages, so a run that dies says how far it got. */
    private const EVERY = 25;

    /** PHP's errors that end the run (a shutdown function is all that sees them). */
    private const FATAL = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

    /** How far this run got: the pages begun, the one being read, and whether it reached its end. */
    private int $read = 0;

    private ?string $page = null;

    private bool $finished = false;

    public function handle(SitemapPaths $pages, Kernel $kernel): int
    {
        $build = config('app.build');
        $last = Cache::get(self::LAST);
        if ($this->option('if-new-build') && (! is_string($build) || $build === '' || (is_array($last) && ($last['build'] ?? null) === $build))) {
            $this->info('This build has been dated already.');

            return self::SUCCESS;
        }

        // Saved before anything else, so a run that never gets further still says it began.
        Cache::forever(self::LAST, ['at' => now()->utc()->toIso8601ZuluString(), 'build' => $build, 'state' => 'started', 'read' => 0, 'of' => null]);
        $paths = SitemapPaths::hashed($pages->all());
        $known = PageVersion::query()->whereIn('path', $paths)->get()->keyBy('path');
        $changed = 0;
        $skipped = [];
        $started = microtime(true);
        $summary = function (string $state, int $read) use (&$changed, &$skipped, $paths, $started): array {
            return ['at' => now()->utc()->toIso8601ZuluString(), 'build' => config('app.build'), 'state' => $state, 'read' => $read, 'of' => count($paths),
                'dated' => $changed, 'skipped' => count($skipped), 'skippedPages' => array_slice($skipped, 0, self::NAMED), 'seconds' => (int) round(microtime(true) - $started)];
        };
        Cache::forever(self::LAST, $summary('started', 0));

        // A fatal error or a kill never reaches the end: the summary says the run died, and where.
        register_shutdown_function(function () use ($summary): void {
            if ($this->finished) {
                return;
            }
            $died = self::died($summary('died', $this->read), $this->page, error_get_last());
            fwrite(STDERR, 'app:sitemap:dates died at '.($died['page'] ?? 'the start').($died['error'] !== null ? ': '.$died['error'] : '').PHP_EOL);

            try {
                Cache::forever(self::LAST, $died);
            } catch (Throwable $e) {
                fwrite(STDERR, 'app:sitemap:dates could not save that: '.$e->getMessage().PHP_EOL);
            }
        });

        foreach ($paths as $path) {
            $this->page = $path;
            if ($this->read > 0 && $this->read % self::EVERY === 0) {
                Cache::forever(self::LAST, $summary('started', $this->read));
            }
            $this->read++;
            $request = Request::create(Hosts::publicUrl().$path, 'GET');
            $request->attributes->set(Limits::UNLIMITED, true);

            try {
                $response = $kernel->handle($request);
                $kernel->terminate($request, $response);
            } catch (Throwable $e) {
                Log::warning('app:sitemap:dates skipped a page that threw', ['path' => $path, 'exception' => $e::class, 'message' => $e->getMessage()]);
                $skipped[] = $path.' ('.class_basename($e).')';

                continue;
            }

            if ($response->getStatusCode() !== 200) {
                Log::warning('app:sitemap:dates skipped a page', ['path' => $path, 'status' => $response->getStatusCode()]);
                $skipped[] = $path.' ('.$response->getStatusCode().')';

                continue;
            }

            $hash = self::hash((string) $response->getContent());
            $page = $known->get($path);

            if ($page === null) {
                PageVersion::query()->create(['path' => $path, 'content_hash' => $hash, 'changed_at' => now()]);
                $changed++;
            } elseif ($page->content_hash !== $hash) {
                $page->forceFill(['content_hash' => $hash, 'changed_at' => now()])->save();
                $changed++;
            }
        }

        PageVersion::query()->whereNotIn('path', $paths)->delete();
        $named = array_slice($skipped, 0, self::NAMED);
        $more = count($skipped) - count($named);
        $this->info(sprintf('%d pages read, %d dated now, %d skipped', count($paths), $changed, count($skipped))
            .($named === [] ? '.' : ': '.implode(', ', $named).($more > 0 ? " and {$more} more." : '.')));
        Cache::forever(self::LAST, $summary('finished', count($paths)));
        $this->finished = true;

        return self::SUCCESS;
    }

    /**
     * The summary of a run that ended before it finished: the page it was on (null before the first) and PHP's last
     * error when that is what ended it (a fatal one; its message, never more than a line).
     *
     * @param  array<string, mixed>  $summary
     * @param  array{type: int, message: string, file: string, line: int}|null  $error
     * @return array<string, mixed>
     */
    public static function died(array $summary, ?string $page, ?array $error): array
    {
        $fatal = $error !== null && in_array($error['type'], self::FATAL, true);

        return [...$summary, 'state' => 'died', 'page' => $page, 'error' => $fatal ? mb_substr(strtok($error['message'], "\n") ?: '', 0, 300) : null];
    }

    /**
     * What a reader sees of a page: its title, its description and its main text, whitespace evened out.
     */
    public static function hash(string $html): string
    {
        $pick = static fn (string $pattern): string => preg_match($pattern, $html, $m) === 1 ? $m[1] : '';
        $title = $pick('#<title[^>]*>(.*?)</title>#is');
        $description = $pick('#<meta\s+name="description"\s+content="([^"]*)"#is');
        $main = $pick('#<main\b[^>]*>(.*)</main>#is');
        $main = $main !== '' ? $main : $pick('#<body\b[^>]*>(.*)</body>#is');
        // Scripts and styles aren't read; neither are tags and their attributes.
        $main = (string) preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#is', ' ', $main);
        $text = html_entity_decode(strip_tags($main), ENT_QUOTES | ENT_HTML5);
        $words = static fn (string $s): string => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5)));

        return hash('sha256', $words($title)."\n".$words($description)."\n".$words($text));
    }
}
