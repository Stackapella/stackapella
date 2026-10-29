<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\WebAppController;
use App\Support\Web\AppShell;
use ReflectionClassConstant;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Every part of /web the app is made of is served, by Laravel (WebAppController) and by nginx in
 * production (.do/nginx.conf), and only the folders the image leaves out aren't (.do/Dockerfile).
 * A new top-level folder fails here, not on the beta (web/course was a 404 there once).
 */
final class WebFoldersTest extends TestCase
{
    /** The folders the image deletes: tests, tools and the spike, never served. */
    private const NOT_SERVED = ['bin', 'spike_audio', 'tests', 'tool'];

    public function test_the_folders_left_out_are_the_ones_the_image_deletes(): void
    {
        preg_match('#rm -rf ((?:/app/web/\S+\s*)+)#', $this->repo('.do/Dockerfile'), $match);
        $deleted = array_map(static fn (string $path): string => substr($path, strlen('/app/web/')), preg_split('/\s+/', trim($match[1] ?? '')) ?: []);
        sort($deleted);

        $this->assertSame(self::NOT_SERVED, $deleted);
    }

    public function test_every_top_level_folder_and_file_of_web_is_served_by_laravel_and_nginx(): void
    {
        $served = (new ReflectionClassConstant(WebAppController::class, 'SERVED'))->getValue();
        $this->assertIsArray($served);

        $nginx = $this->repo('.do/nginx.conf');
        $this->assertSame(1, preg_match('#location ~ \^/\(([^)]+)\)/ \{#', $nginx, $folders), 'nginx.conf has no static folders location.');
        $this->assertSame(1, preg_match('#location ~ \^/\(([^)]+)\)\$ \{#', $nginx, $files), 'nginx.conf has no static files location.');
        $nginxFolders = explode('|', $folders[1]);
        $nginxFiles = array_map(static fn (string $file): string => str_replace('\\.', '.', $file), explode('|', $files[1]));

        foreach (scandir(app(AppShell::class)->webPath()) ?: [] as $entry) {
            $path = app(AppShell::class)->webPath().'/'.$entry;

            if (str_starts_with($entry, '.') || in_array($entry, self::NOT_SERVED, true) || $entry === 'index.html') {
                continue;
            }

            if (is_dir($path)) {
                $this->assertContains($entry.'/', $served, "web/{$entry}/ isn't in WebAppController::SERVED.");
                $this->assertContains($entry, $nginxFolders, "web/{$entry}/ isn't in nginx.conf's static folders.");
            } else {
                $this->assertContains($entry, $served, "web/{$entry} isn't in WebAppController::SERVED.");
                $this->assertContains($entry, $nginxFiles, "web/{$entry} isn't in nginx.conf's static files.");
            }
        }
    }

    public function test_every_file_the_service_worker_precaches_is_served(): void
    {
        $worker = (string) file_get_contents(app(AppShell::class)->webPath().'/sw.js');
        $this->assertSame(1, preg_match('/const SHELL = \[(.*?)\];/s', $worker, $shell), 'sw.js has no SHELL list.');
        preg_match_all("#'(/[^']*)'#", $shell[1], $paths);
        $this->assertNotEmpty($paths[1]);

        foreach ($paths[1] as $path) {
            if ($path === '/' || $path === '/index.html') {
                continue;
            }

            $response = $this->get($path);
            $response->assertOk();
            $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse, "{$path} (precached by sw.js) isn't served as a file.");
        }
    }

    private function repo(string $path): string
    {
        return (string) file_get_contents(base_path('../'.$path));
    }
}
