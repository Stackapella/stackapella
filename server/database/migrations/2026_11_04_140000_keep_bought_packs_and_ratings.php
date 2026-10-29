<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A coach's deleted account no longer takes away the exercise packs people bought (unbought
    // ones are deleted with it), and a singer's no longer takes away their rating of a coach: it
    // stays without their name, so the coach's average stays true. Only the keys change.
    public function up(): void
    {
        Schema::table('exercise_packs', function (Blueprint $table) {
            $table->dropForeign(['coach_id']);
        });
        Schema::table('exercise_packs', function (Blueprint $table) {
            $table->foreignId('coach_id')->nullable()->change();
            $table->foreign('coach_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('coach_ratings', function (Blueprint $table) {
            $table->dropForeign(['singer_id']);
        });
        Schema::table('coach_ratings', function (Blueprint $table) {
            $table->foreignId('singer_id')->nullable()->change();
            $table->foreign('singer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Rows without a coach or a singer can't go back under the old keys.
    }
};
