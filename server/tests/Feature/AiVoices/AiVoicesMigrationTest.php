<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Models\TemplateGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The source column's migration over guides that were there before it: every one recorded, safe to run again.
 */
final class AiVoicesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_guides_are_recorded_and_the_migrations_run_twice(): void
    {
        $guide = TemplateGuide::factory()->create();
        DB::table('template_guides')->where('id', $guide->id)->update(['source' => '']);

        foreach (['2026_11_20_100000_create_ai_voices_table', '2026_11_20_100100_add_source_to_template_guides', '2026_11_20_100200_create_ai_uploads_table'] as $name) {
            $migration = require database_path("migrations/{$name}.php");
            $migration->up();
            $migration->up();
        }

        $this->assertSame('recorded', DB::table('template_guides')->where('id', $guide->id)->value('source'));
        $this->assertTrue(Schema::hasColumn('template_guides', 'ai_voice_id'));
        $this->assertTrue(Schema::hasTable('ai_uploads'));
    }
}
