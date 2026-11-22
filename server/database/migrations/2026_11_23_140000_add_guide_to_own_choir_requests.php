<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A clip sung with no headphones (Hillel, 2026-11-23): nothing played while recording, the tempo kept by eye. Every
 * earlier clip was sung with the guide. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('own_choir_requests', 'guide')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->boolean('guide')->default(true)->after('clip_transpose');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'guide')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropColumn('guide');
            });
        }
    }
};
