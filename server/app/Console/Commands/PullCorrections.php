<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Internal\CorrectionsExportController;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * The readers' open corrections on the song and course pages, pulled for the day's triage
 * (Internal\CorrectionsExportController) into <dir>/YYYY-MM-DD.json: the courses lead's machine has the token
 * (CORRECTIONS_EXPORT_TOKEN in server/.env) and never production access. Read-only; the token is never printed.
 */
final class PullCorrections extends Command
{
    protected $signature = 'app:pull-corrections {--to= : The folder to write YYYY-MM-DD.json in (default CORRECTIONS_EXPORT_DIR)} {--url= : The server (default CORRECTIONS_EXPORT_URL)}';

    protected $description = 'Pull the readers\' open song and course corrections into a dated JSON file, read-only';

    public function handle(): int
    {
        $token = (string) config('services.corrections_export.token');
        if (strlen($token) < CorrectionsExportController::MIN_TOKEN_LENGTH) {
            $this->error('CORRECTIONS_EXPORT_TOKEN is missing or shorter than '.CorrectionsExportController::MIN_TOKEN_LENGTH.' characters: the same value goes in server/.env here and on the server.');

            return self::FAILURE;
        }
        $dir = (string) ($this->option('to') ?: config('services.corrections_export.dir'));
        if ($dir === '') {
            $this->error('Where to? Pass --to=<folder> or set CORRECTIONS_EXPORT_DIR.');

            return self::FAILURE;
        }
        $base = rtrim((string) ($this->option('url') ?: config('services.corrections_export.url')), '/');
        if (($problem = self::urlProblem($base)) !== null) {
            $this->error("Refusing {$base}: {$problem}");

            return self::FAILURE;
        }

        try {
            $response = Http::withToken($token)->acceptJson()->withUserAgent('Stackapella-PullCorrections/1')->timeout(60)
                ->withOptions(['allow_redirects' => false])->get($base.'/api/internal/corrections');
        } catch (ConnectionException) {
            $this->error("Could not connect to {$base}.");

            return self::FAILURE;
        }
        if (($problem = self::failure($response)) !== null) {
            $this->error($problem);

            return self::FAILURE;
        }
        $data = $response->json();
        if (! is_array($data) || ! is_array($data['corrections'] ?? null)) {
            $this->error('The answer was not the corrections (no corrections list). Nothing was saved.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($dir);
        $file = rtrim($dir, '/').'/'.now()->toDateString().'.json';
        File::put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->info(count($data['corrections']).' open corrections saved to '.$file.'.');

        return self::SUCCESS;
    }

    /** Why the token must not go to this URL, or null: only CORRECTIONS_EXPORT_URL's host or a local install, and https unless local. */
    public static function urlProblem(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $local = in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test');
        $configured = strtolower((string) parse_url((string) config('services.corrections_export.url'), PHP_URL_HOST));

        return match (true) {
            $host === '' => 'not a URL.',
            ! $local && $host !== $configured => "the token goes only to CORRECTIONS_EXPORT_URL's host ({$configured}) or a local install.",
            $scheme !== 'https' && ! ($scheme === 'http' && $local) => 'the token would travel in the clear: use https (plain http only for localhost or *.test).',
            default => null,
        };
    }

    /** The failure named from the status alone, never the body. */
    public static function failure(Response $response): ?string
    {
        $status = $response->status();

        return match (true) {
            $response->redirect() => "Redirected ({$status}): set --url or CORRECTIONS_EXPORT_URL to the final address.",
            $status === 401 => 'Token mismatch: CORRECTIONS_EXPORT_TOKEN here differs from the server\'s.',
            $status === 404 => 'Not found: the server has no CORRECTIONS_EXPORT_TOKEN yet (it\'s off until Hillel sets it), or the endpoint isn\'t deployed.',
            $status === 429 => 'Rate limited: try again in a minute.',
            ! $response->successful() => "The server answered {$status}.",
            default => null,
        };
    }
}
