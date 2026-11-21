<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The free choir song, once per email address ever (the coordinator for Hillel, 2026-11-21): a keyed one-way hash of
 * the normalised address, and of an Apple or Google account id, kept after the account is deleted (as
 * trial_fingerprints are), so deleting and signing up again never makes a new free song. Nothing else is kept: the
 * song it was for is set to null when that goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('own_choir_free_songs')) {
            Schema::create('own_choir_free_songs', function (Blueprint $table): void {
                $table->id();
                $table->char('key_hash', 64)->unique();
                $table->foreignId('own_choir_request_id')->nullable()->constrained('own_choir_requests')->nullOnDelete();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('own_choir_free_songs');
    }
};
