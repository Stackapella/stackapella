<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game mode's boards (Hillel: "i love the game ideas, go go go"): arcade initials, the daily
 * challenge's scores, and the ghost of a singer's best take on a part.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safe on the live users table: added at the end, nullable or with a default, so MySQL 8
        // adds them instantly (no copy, no long lock).
        Schema::table('users', function (Blueprint $table): void {
            $table->char('initials', 3)->nullable();
            $table->string('boards_name', 8)->default('name');
        });

        Schema::create('daily_scores', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 32);
            $table->unsignedSmallInteger('scoring_version');
            $table->unsignedTinyInteger('score');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['date', 'user_id']);
            $table->index(['date', 'score']);
        });

        Schema::create('ghosts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 32);
            $table->unsignedSmallInteger('scoring_version');
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('rate');
            $table->decimal('start_seconds', 8, 2);
            $table->smallInteger('key_offset');
            $table->binary('pitches');
            $table->timestamps();
            $table->unique(['user_id', 'template_version_id', 'part_key', 'scoring_version'], 'ghosts_one_per_board');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghosts');
        Schema::dropIfExists('daily_scores');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['initials', 'boards_name']);
        });
    }
};
