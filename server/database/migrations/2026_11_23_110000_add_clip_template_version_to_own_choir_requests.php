<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A choir clip is Happy Birthday's tune, whatever song is being made (Hillel, 2026-11-23): a request remembers the
 * template its clip sang, apart from the song. Null on older requests: their clip sang the song's own tune. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('own_choir_requests', 'clip_template_version_id')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->foreignId('clip_template_version_id')->nullable()->after('template_version_id')->constrained('template_versions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'clip_template_version_id')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('clip_template_version_id');
            });
        }
    }
};
