<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ideas (docs/your-tune-plan.md, step 3): a writer's tune shared with every part open, the
    // voices singers add to it, the bell's link to it, and the writer's email setting. New tables
    // and a defaulted or nullable column each: safe on the live database.
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 12)->unique();
            $table->foreignId('template_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('note', 300)->nullable();
            $table->unsignedTinyInteger('voicing');
            $table->json('part_keys');
            $table->timestamp('rights_confirmed_at');
            $table->timestamp('held_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->unsignedInteger('voices_count')->default(0);
            $table->timestamps();

            $table->index(['hidden_at', 'held_at', 'created_at']);
        });

        Schema::create('idea_voices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 40);
            $table->string('status', 12);
            $table->string('failure', 24)->nullable();
            $table->unsignedInteger('sample_rate');
            $table->unsignedInteger('frames');
            $table->integer('offset_frames')->default(0);
            $table->unsignedBigInteger('upload_bytes');
            $table->char('sha256', 64);
            $table->string('upload_key', 255);
            $table->string('file_key', 255)->nullable();
            $table->unsignedBigInteger('stored_bytes')->nullable();
            // Its outline, as a take's (Peaks::framesForTake), so the idea's page draws it.
            $table->unsignedInteger('frames_per_peak')->nullable();
            $table->json('peaks')->nullable();
            $table->timestamp('rights_confirmed_at');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['idea_id', 'status']);
            $table->index(['status', 'notified_at']);
        });

        Schema::table('bell_items', function (Blueprint $table) {
            $table->foreignId('idea_id')->nullable()->after('harmony_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('idea_emails')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('idea_emails');
        });

        Schema::table('bell_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('idea_id');
        });

        Schema::dropIfExists('idea_voices');
        Schema::dropIfExists('ideas');
    }
};
