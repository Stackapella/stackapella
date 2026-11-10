<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What we last told IndexNow (Hillel, 2026-11-09: "Yes, build it"): each public page we submitted,
 * with the lastmod it had then, so a page goes again only when it's new to the sitemap or its words
 * changed. Our own public addresses only; nothing about anyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexnow_urls', function (Blueprint $table): void {
            $table->id();
            $table->char('path_hash', 64)->unique();
            $table->text('path');
            $table->date('lastmod')->nullable();
            $table->dateTime('submitted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexnow_urls');
    }
};
