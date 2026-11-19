<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real recordings on YouTube synced to our songs (docs/youtube-sync-plan.md; Hillel, 2026-11-19). A row is our own
 * sync map (the form and the tapped anchors) plus the video's id and the few facts YouTube's last check gave, which
 * YouTube's Developer Policies let us keep for 30 days at most (III.E.4.d): `checked_at` is never older than that, or
 * the row is deleted by app:videos:recheck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('template_version');
            $table->string('video_id', 11);
            $table->string('status', 16)->default('draft');
            $table->string('hidden_reason', 32)->nullable();
            $table->string('title', 200);
            $table->string('channel_title', 200);
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('checked_at');
            $table->json('form');
            $table->json('anchors');
            $table->string('harmony', 16)->default('same');
            $table->json('other_words')->nullable();
            $table->foreignId('synced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'video_id']);
            $table->index(['template_id', 'status']);
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_videos');
    }
};
