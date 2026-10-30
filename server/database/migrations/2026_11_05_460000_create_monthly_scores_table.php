<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Game mode's monthly boards (so a newcomer can place): each person's best counted score on a part
 * this calendar month (UTC). Filled from this month's part scores, so the first board isn't
 * empty. A new table: live-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_scores', function (Blueprint $table): void {
            $table->id();
            $table->char('month', 7);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('part_key', 32);
            $table->unsignedSmallInteger('scoring_version');
            $table->unsignedTinyInteger('score');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['month', 'user_id', 'template_version_id', 'part_key', 'scoring_version'], 'monthly_scores_one_per_board');
            $table->index(['month', 'template_version_id', 'part_key', 'score'], 'monthly_scores_board');
        });

        $start = now()->utc()->startOfMonth();
        DB::statement('insert into monthly_scores (month, user_id, template_version_id, part_key, scoring_version, score, recorded_at, created_at, updated_at)
            select ?, user_id, template_version_id, part_key, scoring_version, score, recorded_at, ?, ? from part_scores where recorded_at >= ?', [$start->format('Y-m'), now(), now(), $start]);
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_scores');
    }
};
