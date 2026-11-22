<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Choir settings (Hillel, 2026-11-23, Plus to change): a song's mix (`sound`) and how it's sung (`sung`); a remake is a
 * child request (`remake`) whose parts replace the song's when made. Null settings are the defaults. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('own_choir_requests', 'sound')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->json('sound')->nullable()->after('voicing');
                $table->json('sung')->nullable()->after('sound');
                $table->boolean('remake')->default(false)->after('sung');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'sound')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropColumn(['sound', 'sung', 'remake']);
            });
        }
    }
};
