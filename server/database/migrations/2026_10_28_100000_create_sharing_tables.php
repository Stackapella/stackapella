<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Sharing (contract/api.md, "Sharing: harmonies, videos and reports"): the mixes people share,
    // the videos made from them, reports from anyone, and sign-ups that came from a share.
    public function up(): void
    {
        Schema::create('harmonies', function (Blueprint $table) {
            $table->id();
            // The link's only secret, so random rather than a Sqid of the sequential key: an
            // unlisted harmony can't be found by counting.
            $table->char('public_id', 12)->charset('ascii')->collation('ascii_bin')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The harmony is its own record: it outlives the project and the part.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            // Just my part: the part's name, kept after the part itself is gone.
            $table->string('part_name', 60)->nullable();
            $table->uuid('change_id');
            $table->string('title', 120)->default('');
            $table->string('visibility', 16);
            $table->string('status', 16);
            $table->string('failure', 24)->nullable();
            $table->unsignedInteger('sample_rate');
            $table->unsignedTinyInteger('channels');
            $table->unsignedInteger('frames');
            $table->unsignedBigInteger('upload_bytes');
            $table->char('sha256', 64);
            $table->string('upload_key');
            // The AAC and the card, kept private; copied to `media` only while the page is live.
            $table->string('audio_key')->nullable();
            $table->string('card_key')->nullable();
            $table->string('media_audio_key')->nullable();
            $table->string('media_card_key')->nullable();
            $table->unsignedBigInteger('stored_bytes')->nullable();
            $table->json('peaks')->nullable();
            // Each part's name, colour and outline (base64, a byte per 50 ms), from the device.
            $table->json('tracks');
            $table->json('guide_ids');
            // For the video's words: the template, the part sung, the tempo and where beat 0 is.
            $table->foreignId('template_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('words_part_key', 40)->nullable();
            $table->decimal('bpm', 6, 2)->nullable();
            $table->unsignedInteger('beat_zero_ms')->nullable();
            $table->timestamp('title_held_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason', 16)->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->unsignedInteger('sign_ups')->default(0);
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'change_id']);
            $table->index(['user_id', 'deleted_at', 'id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('share_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harmony_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('start_ms');
            $table->unsignedInteger('end_ms');
            $table->string('look', 8);
            $table->boolean('words');
            $table->string('status', 16);
            $table->decimal('progress', 4, 3)->default(0);
            $table->string('failure', 24)->nullable();
            $table->string('file_key')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['harmony_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        // Reports on anything shared (harmonies first; comments, profiles and templates later).
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->morphs('reportable');
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            // Who it counts as: the account, or the network's HMAC when signed out.
            $table->char('reporter_key', 64);
            $table->char('ip_hash', 64);
            $table->string('reason', 16);
            $table->text('note');
            $table->string('status', 16)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['reportable_type', 'reportable_id', 'reporter_key']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('signup_harmony_id')->nullable()->constrained('harmonies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signup_harmony_id');
        });
        Schema::dropIfExists('reports');
        Schema::dropIfExists('share_videos');
        Schema::dropIfExists('harmonies');
    }
};
