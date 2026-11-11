<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The opened_at migration over a library that already has songs (2026-11-11: its first form read a column versions
 * don't have, and stopped after adding the column, so every run after failed): it fills each open song's day, leaves a
 * hidden one empty, and runs again harmlessly.
 */
final class OpenedAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_each_open_songs_day_and_runs_again_harmlessly(): void
    {
        $open = Template::factory()->create(['opened_at' => null]);
        $openVersion = TemplateVersion::factory()->for($open)->create(['reviewed' => true, 'created_at' => '2026-11-03 10:00:00']);
        $open->forceFill(['current_version_id' => $openVersion->id])->save();
        $hidden = Template::factory()->create(['opened_at' => null]);
        $hiddenVersion = TemplateVersion::factory()->for($hidden)->create(['reviewed' => false]);
        $hidden->forceFill(['current_version_id' => $hiddenVersion->id])->save();

        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_11_220000_add_opened_at_to_templates.php');
        $migration->up();
        $migration->up();

        $this->assertSame('2026-11-03', $open->refresh()->opened_at?->toDateString());
        $this->assertNull($hidden->refresh()->opened_at);
    }
}
