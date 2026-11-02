<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Query speed for Admin's counts (docs/performance.md, the 2026-11-01 pass): Growth, Sources, the
 * funnel, Usage and people using the app each read a few kinds over a range of days, which the
 * day-first unique key couldn't start from (Reach took 950 ms on 180 days of counts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_counts', fn (Blueprint $table) => $table->index(['kind', 'day']));
    }

    public function down(): void
    {
        Schema::table('usage_counts', fn (Blueprint $table) => $table->dropIndex(['kind', 'day']));
    }
};
