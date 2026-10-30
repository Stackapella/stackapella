<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Growth's Sources (docs/admin-growth-plan.md, slice 4): the funnel's counts and landing visits
 * carry where people came from, grouped from our own ?ref= links (a campaign tag, "harmony",
 * "coach", "other"; '' for none). A short tag many share, never an id. Live-safe: the column is
 * added at the end with a default (instant), and the table is small daily totals, so swapping
 * its unique key is quick; the new key goes on before the old comes off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->string('source', 16)->default('');
        });

        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->unique(['day', 'kind', 'audience', 'country', 'variant', 'source'], 'usage_counts_cell_unique');
            $table->dropUnique(['day', 'kind', 'audience', 'country', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->unique(['day', 'kind', 'audience', 'country', 'variant']);
            $table->dropUnique('usage_counts_cell_unique');
            $table->dropColumn('source');
        });
    }
};
