<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Query speed (2026-11-02): outreach's daily cap counts today's sends by when they went (its old
 * index led with the status, so the count read every row of it); outreach's results and Growth's
 * sources add the counts up by where people came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->index('sent_at');
        });

        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->index(['source', 'kind'], 'usage_counts_source_kind_index');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', fn (Blueprint $table) => $table->dropIndex(['sent_at']));
        Schema::table('usage_counts', fn (Blueprint $table) => $table->dropIndex('usage_counts_source_kind_index'));
    }
};
