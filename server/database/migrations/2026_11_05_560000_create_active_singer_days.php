<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Active singers worked out once a day (docs/admin-analytics-plan.md, slice 1): each day's count
 * of signed-in people who sang, practised or had a lesson in the 7 and the 30 days ending that
 * day. Counts only, never who.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_singer_days', function (Blueprint $table): void {
            $table->date('day')->primary();
            $table->unsignedInteger('week');
            $table->unsignedInteger('month');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_singer_days');
    }
};
