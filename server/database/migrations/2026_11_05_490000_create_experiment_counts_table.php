<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Experiments' own counts (docs/experiments-plan.md, slice 2): each funnel step a device in an
 * experiment reaches, a daily total per arm, apart from usage_counts so the everyday totals count
 * each device once however many experiments it's in. No country, nothing per person. A new table:
 * live-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiment_counts', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('variant', 32);
            $table->string('kind', 32);
            $table->string('audience', 8);
            $table->unsignedInteger('count')->default(0);
            $table->unique(['day', 'variant', 'kind', 'audience'], 'experiment_counts_cell');
            $table->index(['variant', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_counts');
    }
};
