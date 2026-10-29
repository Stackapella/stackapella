<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_checks_the_database_and_cache(): void
    {
        $this->get('/up')->assertOk()->assertJson(['status' => 'ok'])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_a_framework_default_name_in_production_is_reported_as_degraded(): void
    {
        $this->get('/up')->assertOk()->assertJsonPath('status', 'ok');

        $this->app['env'] = 'production';
        config(['mail.from.name' => 'Laravel']);

        $this->get('/up')->assertOk()->assertJsonPath('status', 'degraded');
    }

    public function test_an_ffmpeg_without_h264_or_aac_is_degraded_in_production_and_asked_once_an_hour(): void
    {
        $this->app['env'] = 'production';
        $this->get('/up')->assertJsonPath('status', 'ok');

        Cache::forget('health:ffmpeg-encoders');
        Process::fake(['*-encoders*' => Process::result(" A....D aac                  AAC (Advanced Audio Coding)\n V....D h264_videotoolbox    VideoToolbox H.264 Encoder\n")]);

        $this->get('/up')->assertJsonPath('status', 'degraded');
        $this->get('/up')->assertJsonPath('status', 'degraded');
        Process::assertRanTimes(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), '-encoders'), 1);
    }

    public function test_up_says_which_build_and_when_it_was_made(): void
    {
        config(['app.build' => '8a88277f0c2e', 'app.built_at' => '2026-10-26T19:42:07Z']);

        $this->get('/up')->assertJsonPath('build', '8a88277f0c2e')->assertJsonPath('builtAt', '2026-10-26T19:42:07Z');
    }
}
