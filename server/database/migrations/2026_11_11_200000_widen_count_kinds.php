<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A count's kind may be up to 64 characters (was 32): a song course's lesson step (cs_r:<lessonId>.<step>, its
 * ids run to 50) and a tool's share (sh:tool-<tool>.<method>) need the room (docs/retention-plan.md, section 8).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['usage_counts', 'usage_count_hours', 'experiment_counts'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('kind', 64)->change());
        }
    }

    public function down(): void
    {
        foreach (['usage_counts', 'usage_count_hours', 'experiment_counts'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('kind', 32)->change());
        }
    }
};
