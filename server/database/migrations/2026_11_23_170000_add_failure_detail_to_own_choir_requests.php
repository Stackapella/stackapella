<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a clip couldn't be used (Hillel, 2026-11-23: "so they know why it failed, to fix it next time"): the worker's
 * reason code, shown to the singer alone. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('own_choir_requests', 'failure_detail')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->string('failure_detail', 20)->nullable()->after('failure');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'failure_detail')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropColumn('failure_detail');
            });
        }
    }
};
