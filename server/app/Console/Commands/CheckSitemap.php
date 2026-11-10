<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Http\Hosts;
use App\Support\Ops\SitemapProblems;
use App\Support\Site\SitemapCheck;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Walks our sitemap as a search engine would and names every page that can't be indexed, grouped
 * by why (SitemapCheck). Read-only and polite: one request at a time, a pause between them, our
 * own User-Agent and nothing about anyone in any header. Exits 1 when it finds a problem.
 *
 *   php artisan app:sitemap:check --base=https://stackapella.com
 *
 * Production's scheduler runs it on our own public site every Wednesday early (--save), before
 * Hillel's weekly search review; what it finds shows in Readiness and admin's overview.
 */
final class CheckSitemap extends Command
{
    /** Who we are to the server we ask: no person, no address. */
    public const USER_AGENT = 'StackapellaSitemapCheck/1.0';

    protected $signature = 'app:sitemap:check
        {--base= : The site to check (default: our public site)}
        {--delay=300 : Milliseconds between requests}
        {--all : List every address, not the first 5 of each kind}
        {--save : Keep the result for Readiness and admin (the weekly run)}';

    protected $description = 'Check every sitemap page can be indexed (200, its own canonical, no noindex, allowed) and every hub-linked page is in it';

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base') ?: Hosts::publicUrl()), '/');
        $delay = max(0, (int) $this->option('delay'));
        $asked = 0;

        $fetch = function (string $url) use ($delay, &$asked): array {
            if ($asked++ > 0 && $delay > 0) {
                usleep($delay * 1000);
            }

            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'text/html,application/xml;q=0.9,*/*;q=0.8'])
                    ->withoutRedirecting()->timeout(30)
                    // Again only when the connection failed: an answer, even an error, is the answer.
                    ->retry(2, 2000, static fn (?\Throwable $e): bool => $e instanceof ConnectionException, throw: false)->get($url);
            } catch (ConnectionException $e) {
                $this->warn("{$url}: {$e->getMessage()}");

                return ['status' => 0, 'headers' => [], 'body' => ''];
            }

            return [
                'status' => $response->status(),
                'headers' => array_change_key_case(array_map(static fn (array $values): string => implode(', ', $values), $response->headers())),
                'body' => $response->body(),
            ];
        };

        $this->line("Checking {$base}/sitemap.xml...");
        $problems = (new SitemapCheck($fetch(...)))->run($base);
        $this->line("{$asked} pages asked.");

        if ($this->option('save')) {
            $lines = [];
            foreach ($problems as $kind => $found) {
                foreach ($found as $problem) {
                    $lines[] = SitemapCheck::KINDS[$kind].': '.$problem['url'].($problem['detail'] !== '' ? ' ('.$problem['detail'].')' : '');
                }
            }
            SitemapProblems::save($base, $lines);
        }

        if ($problems === []) {
            $this->info('Every sitemap page can be indexed, and every page they link to is in the sitemap or says noindex.');

            return self::SUCCESS;
        }

        foreach ($problems as $kind => $found) {
            $this->newLine();
            $this->error(SitemapCheck::KINDS[$kind].': '.count($found));

            foreach ($this->option('all') ? $found : array_slice($found, 0, 5) as $problem) {
                $this->line('  '.$problem['url'].($problem['detail'] !== '' ? ' ('.$problem['detail'].')' : ''));
            }
        }

        return self::FAILURE;
    }
}
