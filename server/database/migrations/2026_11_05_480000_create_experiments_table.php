<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The experiments registry (docs/experiments-plan.md, slice 1; Hillel: "GO EXPERIMENTS"): A/B
 * tests defined in Admin, not in config. onb1 moves in exactly as config/counts.php had it. A new
 * table: live-safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 24)->unique();
            $table->string('what', 200);
            $table->string('hypothesis', 500)->default('');
            $table->json('arms');
            $table->string('metric', 48);
            $table->json('guardrails');
            $table->json('audience');
            $table->json('excludes');
            $table->unsignedInteger('planned_per_arm');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('winner', 8)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('experiments')->insert([
            'key' => 'onb1', 'what' => 'The chord moment after lesson 1\'s note', 'hypothesis' => '',
            'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_lesson3', 'guardrails' => json_encode(['f_lesson2']), 'audience' => json_encode(['new' => true, 'firstWeek' => true]),
            'excludes' => json_encode([]), 'planned_per_arm' => 1500,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};
