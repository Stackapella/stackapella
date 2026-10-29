<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Song requests (N7; docs/more-features-plan.md, 8): what people want to sing next, their
    // votes, and the bell's link to one. New tables and a nullable column: safe live.
    public function up(): void
    {
        Schema::create('song_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->string('match_key', 120)->index();
            $table->string('writer', 120)->nullable();
            $table->string('why', 300)->nullable();
            $table->string('status', 12)->default('requested');
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merged_into_id')->nullable()->constrained('song_requests')->nullOnDelete();
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamp('held_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['hidden_at', 'held_at', 'votes_count']);
        });

        Schema::create('song_request_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['song_request_id', 'user_id']);
        });

        Schema::table('bell_items', function (Blueprint $table) {
            $table->foreignId('song_request_id')->nullable()->after('idea_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bell_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('song_request_id');
        });

        Schema::dropIfExists('song_request_votes');
        Schema::dropIfExists('song_requests');
    }
};
