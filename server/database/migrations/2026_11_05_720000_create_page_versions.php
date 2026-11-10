<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each public page last changed in words (the sitemap's lastmod, 2026-11-02): a hash of what
 * a reader sees, and when it last differed. A deploy that changes no words keeps the date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 512)->unique();
            $table->char('content_hash', 64);
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
    }
};
