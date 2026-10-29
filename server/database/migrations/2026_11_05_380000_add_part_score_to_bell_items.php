<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A friend beat your score (the leaderboards' bell item): the score that did it. The item goes with
 * the score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bell_items', function (Blueprint $table): void {
            $table->foreignId('part_score_id')->nullable()->after('song_request_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bell_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('part_score_id');
        });
    }
};
