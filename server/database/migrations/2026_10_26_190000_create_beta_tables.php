<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The beta (docs/beta-singers-plan.md): singers invited by a private link, the parts they're asked
 * to record, the takes they send, the guides accepted from them, and their "Tell Hillel" notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beta_singers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Only for Hillel's records; the app never emails singers.
            $table->string('email')->nullable();
            $table->string('note', 1000)->nullable();
            // The invite link's token, stored only as a SHA-256 hash. A new link replaces it.
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            // Consent: the agreement's version, when it was accepted, and "I'm 18 or over".
            $table->unsignedInteger('consent_version')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('credit_mode', 10)->nullable();
            $table->string('credit_name', 80)->nullable();
            // Payment (docs/decisions.md: singers are paid their hourly rate, outside the app).
            $table->unsignedInteger('hourly_rate_cents')->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The 6-character code that moves a singer's session into the installed iPhone app or
        // another device: short-lived, one use, stored hashed.
        Schema::create('beta_session_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beta_singer_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('beta_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beta_singer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 20);
            // Agreed before they start: the minutes paid for this part once accepted.
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->timestamps();

            $table->unique(['beta_singer_id', 'template_id', 'part_key']);
            $table->index(['template_id', 'part_key']);
        });

        Schema::create('beta_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beta_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 20);
            $table->string('status', 20);
            // The take, as the device describes it, and checked against the upload.
            $table->string('music_key', 8);
            $table->unsignedInteger('sample_rate');
            $table->unsignedBigInteger('frames');
            $table->integer('latency_frames');
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->string('device', 200)->nullable();
            $table->string('mic', 200)->nullable();
            $table->json('scores')->nullable();
            $table->string('singer_note', 1000)->nullable();
            // Files: the WAV master (private) and, once processed, the outline and loudness.
            $table->string('master_key', 255);
            $table->json('peaks')->nullable();
            $table->decimal('loudness_dbfs', 6, 2)->nullable();
            $table->string('failure', 40)->nullable();
            // Hillel's review: light fixes, his note (pinned to a beat), and the decision.
            $table->integer('offset_ms')->default(0);
            $table->unsignedInteger('trim_start_ms')->default(0);
            $table->unsignedInteger('trim_end_ms')->default(0);
            $table->decimal('gain_db', 5, 2)->default(0);
            $table->string('admin_note', 1000)->nullable();
            $table->decimal('admin_note_beat', 8, 3)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['beta_assignment_id', 'id']);
        });

        // A recorded guide on the CDN for one part of one template version. One is the default;
        // others are kept as alternates.
        Schema::create('template_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 20);
            $table->foreignId('beta_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->string('media_key', 255);
            $table->unsignedInteger('sample_rate');
            $table->unsignedBigInteger('frames');
            $table->integer('offset_frames')->default(0);
            $table->decimal('gain', 6, 3)->default(1);
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['template_version_id', 'part_key', 'is_default']);
        });

        Schema::create('beta_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beta_singer_id')->constrained()->cascadeOnDelete();
            $table->string('template_slug', 80)->nullable();
            $table->string('part_key', 20)->nullable();
            $table->decimal('beat', 8, 3)->nullable();
            $table->string('text', 2000);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['read_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beta_feedback');
        Schema::dropIfExists('template_guides');
        Schema::dropIfExists('beta_submissions');
        Schema::dropIfExists('beta_assignments');
        Schema::dropIfExists('beta_session_codes');
        Schema::dropIfExists('beta_singers');
    }
};
