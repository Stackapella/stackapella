<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The singing test's results (docs/singing-test-v1.md): numbers only, never audio, kept by test and scoring version so
 * a new scoring version scores every kept test again. Deleted with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('singing_tests')) {
            return;
        }

        Schema::create('singing_tests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('test_version');
            // What the device scored it with; the server's own scores below are at scored_version.
            $table->unsignedSmallInteger('device_scoring_version');
            $table->timestamp('taken_at');
            $table->decimal('anchor', 4, 1);
            $table->string('device', 10);
            $table->boolean('warm_up')->default(false);
            // match, back and hold: each item's numbers, as sent.
            $table->json('items');
            $table->json('device_score')->nullable();
            $table->unsignedSmallInteger('scored_version');
            $table->string('status', 10);
            $table->decimal('match_points', 5, 1)->nullable();
            $table->decimal('back_points', 5, 1)->nullable();
            $table->decimal('hold_points', 5, 1)->nullable();
            $table->unsignedTinyInteger('total')->nullable();
            $table->string('band', 10)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('singing_tests');
    }
};
