<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly drop-off report (docs/retention-plan.md, "Detect"): one row a week, the week's totals and each path's
 * pass-through, kept so a past week is never worked out again. Anonymous daily totals only: nothing about anyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropoff_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('week_end')->unique();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropoff_reports');
    }
};
