<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search Console's last 28 days by page and by query (docs/search-console.md), replaced by each
 * daily sync: Google's totals for our public pages and the searches that found them, nothing about
 * any visitor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('dimension', 8);
            $table->string('key', 500);
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');
            $table->decimal('ctr', 6, 4);
            $table->decimal('position', 6, 2);
            $table->index(['dimension', 'impressions']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_rows');
    }
};
