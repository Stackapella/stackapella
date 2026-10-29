<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Cloud save (docs/backend-plan.md C1 to C6; contract/api.md, "Projects and cloud save"). Every
    // row a device syncs carries `seq`, from the owner's own counter (users.sync_seq), so
    // sync.pull can ask for "everything since"; what's gone for good leaves a tombstone.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // What Plans reads until billing (M5) takes over.
            $table->string('plan', 12)->default('free')->after('locale');
            $table->unsignedBigInteger('sync_seq')->default(0)->after('plan');
            // The newest seq whose tombstones were pruned: an older cursor is too old (410).
            $table->unsignedBigInteger('sync_pruned_seq')->default(0)->after('sync_seq');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->decimal('bpm', 6, 2);
            $table->string('music_key', 8)->nullable();
            $table->string('time_signature', 8);
            $table->string('template_slug', 80)->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->json('settings');
            $table->unsignedInteger('version')->default(1);
            // The outbox entry that made the last change, so a replay answers as it is.
            $table->uuid('last_change_id')->nullable();
            $table->unsignedBigInteger('seq');
            $table->timestamp('device_created_at')->nullable();
            $table->timestamp('binned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'seq']);
            $table->index(['user_id', 'binned_at']);
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('name', 80);
            $table->string('color', 16);
            $table->decimal('volume', 4, 3);
            $table->decimal('pan', 4, 3);
            $table->boolean('muted');
            $table->boolean('solo');
            // Where the current take plays is the take's own offset, plus this nudge.
            $table->bigInteger('nudge_frames');
            $table->unsignedBigInteger('trim_start_frames');
            $table->unsignedBigInteger('trim_end_frames');
            $table->string('template_part_key', 40)->nullable();
            $table->unsignedBigInteger('current_take_id')->nullable();
            $table->json('settings');
            $table->unsignedInteger('version')->default(1);
            $table->uuid('last_change_id')->nullable();
            $table->unsignedBigInteger('seq');
            $table->timestamp('device_created_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'seq']);
        });

        Schema::create('takes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null once its part is deleted (the take waits in the bin, and can't come back).
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12);
            $table->string('failure', 24)->nullable();
            $table->unsignedInteger('sample_rate');
            $table->unsignedBigInteger('frames');
            // Where it was recorded, at its own rate: immutable, so an Undo plays it where it was.
            $table->bigInteger('offset_frames');
            $table->unsignedBigInteger('upload_bytes');
            $table->unsignedBigInteger('stored_bytes')->nullable();
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('frames_per_peak')->default(512);
            $table->json('peaks')->nullable();
            $table->string('upload_key');
            $table->string('file_key')->nullable();
            // The take this one replaced as its part's current: binned once this one is ready.
            $table->unsignedBigInteger('replaces_take_id')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->unsignedBigInteger('seq');
            $table->timestamp('binned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'seq']);
            $table->index(['user_id', 'binned_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->foreign('current_take_id')->references('id')->on('takes')->nullOnDelete();
        });

        // The score summary with each take, for the history (numbers only, never audio).
        Schema::create('take_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('take_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('scores');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8);
            $table->uuid('uuid');
            $table->unsignedBigInteger('seq');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'seq']);
        });

        Schema::create('user_learning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('progress');
            $table->unsignedInteger('version')->default(1);
            $table->uuid('last_change_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropForeign(['current_take_id']);
        });
        Schema::dropIfExists('user_learning');
        Schema::dropIfExists('sync_tombstones');
        Schema::dropIfExists('take_scores');
        Schema::dropIfExists('takes');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('projects');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan', 'sync_seq', 'sync_pruned_seq']);
        });
    }
};
