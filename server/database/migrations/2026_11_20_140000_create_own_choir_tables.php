<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hear yourself as a choir (docs/ai-voices-build-plan.md, track B phase 3): a person's consent, their voice kept for
 * their next songs (the renderer's prepared reference, never the raw clip), each song requested in their voice (with a
 * clip, deleted the moment the song finishes, or from their kept voice), and each part rendered, private to them.
 * Everything goes with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('own_choir_consents')) {
            Schema::create('own_choir_consents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->timestamp('accepted_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('own_choir_voices')) {
            Schema::create('own_choir_voices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 16)->default('uploading');
                $table->string('upload_key', 120);
                $table->string('reference_key', 120)->nullable();
                $table->unsignedInteger('sample_rate');
                $table->unsignedInteger('frames');
                $table->unsignedBigInteger('bytes');
                $table->char('sha256', 64);
                $table->json('meta');
                // Prepared from a clip of a part with no words (a hum): the app may suggest one sung line for a song with words.
                $table->boolean('wordless')->default(false);
                $table->timestamp('saved_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('own_choir_requests')) {
            Schema::create('own_choir_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
                // Made from their kept voice (null: from a new clip, below).
                $table->foreignId('own_choir_voice_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('from_voice')->default(false);
                $table->string('part_key', 40)->nullable();
                $table->integer('offset_frames')->nullable();
                $table->string('status', 16)->default('uploading');
                $table->string('failure', 40)->nullable();
                $table->string('upload_key', 120)->nullable();
                $table->string('clip_key', 120)->nullable();
                $table->timestamp('clip_checked_at')->nullable();
                $table->unsignedInteger('sample_rate')->nullable();
                $table->unsignedInteger('frames')->nullable();
                $table->unsignedBigInteger('bytes')->nullable();
                $table->char('sha256', 64)->nullable();
                $table->timestamp('claimed_at')->nullable();
                // The token a claim gives the worker: only the claim holding it acts on the job.
                $table->char('claim_token', 32)->nullable();
                $table->unsignedTinyInteger('tries')->default(0);
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('seen_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'claimed_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('own_choir_parts')) {
            Schema::create('own_choir_parts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('own_choir_request_id')->constrained()->cascadeOnDelete();
                $table->string('part_key', 40);
                $table->string('status', 16)->default('uploading');
                $table->string('failure', 40)->nullable();
                $table->string('upload_key', 120);
                $table->string('master_key', 120)->nullable();
                $table->string('media_key', 120)->nullable();
                $table->unsignedInteger('sample_rate');
                $table->unsignedInteger('frames');
                $table->unsignedBigInteger('bytes');
                $table->char('sha256', 64);
                $table->json('checks');
                $table->json('renderer');
                $table->timestamps();
                $table->unique(['own_choir_request_id', 'part_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('own_choir_parts');
        Schema::dropIfExists('own_choir_requests');
        Schema::dropIfExists('own_choir_voices');
        Schema::dropIfExists('own_choir_consents');
    }
};
