<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every presigned upload link (the abuse review, 2026-11-21): the key it opens, the size declared and when it
 * stops, so the sweeper can delete what's bigger than declared, and every version left once it's done.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('upload_links')) {
            return;
        }

        Schema::create('upload_links', function (Blueprint $table): void {
            $table->id();
            $table->string('upload_key', 255)->unique();
            $table->unsignedBigInteger('bytes');
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_links');
    }
};
