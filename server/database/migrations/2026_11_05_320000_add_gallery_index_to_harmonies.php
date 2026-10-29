<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The gallery's newest first (explore.list, docs/performance.md): live, ready, unlisted harmonies
 * by id, so a page reads the newest rows in order and stops, instead of sorting every ready one
 * (a harmony's row is about 24 KB, its peaks and stems).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('harmonies', function (Blueprint $table): void {
            $table->index(['visibility', 'status', 'deleted_at', 'id'], 'harmonies_gallery_index');
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table): void {
            $table->dropIndex('harmonies_gallery_index');
        });
    }
};
