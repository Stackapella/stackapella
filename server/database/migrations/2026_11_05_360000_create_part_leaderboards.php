<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part leaderboards' foundation (docs/decisions.md, "part leaderboards"): each person's best score
 * per song version, part and scoring version, with the facts that made it count; who's connected to
 * whom for friends' boards; challenge links; and the global board's opt-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 32);
            // The shared scoring's version (web/js/scoring.js): boards never mix two.
            $table->unsignedSmallInteger('scoring_version');
            $table->unsignedTinyInteger('score');
            // What made it count, as the app reported it.
            $table->string('mode', 16);
            $table->smallInteger('key_offset')->default(0);
            $table->decimal('speed', 3, 2)->default(1);
            $table->boolean('guide_muted');
            $table->boolean('timing_checked');
            $table->string('output', 16)->nullable();
            // What a rescore needs (a better pitch detector later): the take itself, pinned while the
            // score stands (deleting the take deletes the score), the alignment the app applied, and
            // the per-note results ({"lit", "cents", "timingMs"} each), so a board can be rebuilt and
            // explained without the audio.
            $table->foreignId('take_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('alignment_ms')->nullable();
            $table->unsignedSmallInteger('pitch_detector_version');
            $table->json('notes')->nullable();
            $table->string('app_version', 32)->nullable();
            // Hooks for later: a score held for review, and one an admin took off the boards.
            $table->timestamp('flagged_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['user_id', 'template_version_id', 'part_key', 'scoring_version'], 'part_scores_best');
            $table->index(['template_version_id', 'part_key', 'scoring_version', 'score'], 'part_scores_board');
        });

        Schema::create('board_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            // challenge now; song and group are worked out from projects and groups as boards are read.
            $table->string('source', 12);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'friend_id', 'source']);
        });

        Schema::create('board_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 32);
            $table->char('token_hash', 64)->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('board_global_at')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('board_global_at');
        });
        Schema::dropIfExists('board_challenges');
        Schema::dropIfExists('board_connections');
        Schema::dropIfExists('part_scores');
    }
};
