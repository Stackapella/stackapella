<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The anonymous counts by the hour (Hillel, 2026-11-09: compare today with yesterday "at 10am", and
 * every period pro-rated): the same counts as usage_counts, each in the UTC hour it came in (the
 * hour's start), so a window in any timezone is a range of whole hours. Kind, guest or account, and
 * the source a funnel kind keeps; never a country, an arm or anything about a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_count_hours', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('hour');
            $table->string('kind', 32);
            $table->string('audience', 8);
            $table->string('source', 60)->default('');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['hour', 'kind', 'audience', 'source'], 'usage_count_hours_cell');
            $table->index(['kind', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_count_hours');
    }
};
