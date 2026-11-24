<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The choir gallery (docs/choir-gallery-plan.md): a choir song its singer added, shown as its own share once staff
 * heard that exact mix; the consent's version, the sheet's words hash and time; staff's decision and reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('choir_gallery_items')) {
            return;
        }

        Schema::create('choir_gallery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_choir_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('harmony_id')->nullable()->constrained()->nullOnDelete();
            // The song's parts as added (their files' hashes): a remake changes it, and the item waits for a new add.
            $table->char('parts_sha', 64);
            $table->string('status', 10);
            $table->string('reason', 12)->nullable();
            $table->string('reason_text', 500)->nullable();
            $table->unsignedSmallInteger('consent_version');
            $table->char('words_hash', 64);
            $table->string('locale', 5);
            $table->timestamp('added_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            // Staff released it after reports: only "this is my voice" holds it again.
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'added_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('choir_gallery_items');
    }
};
