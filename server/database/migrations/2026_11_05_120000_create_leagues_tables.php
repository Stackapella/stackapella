<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // N2, leagues and friends' leaderboards (docs/vocal-coaching-plan.md, section 8): weekly
    // leagues of about 30 by tier, each member's capped XP for the week, and the person's tier
    // and opt-in. New tables and nullable columns: safe on the live database.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('league_joined_at')->nullable()->after('public_profile_at');
            $table->unsignedSmallInteger('league_tier')->default(1)->after('league_joined_at');
        });

        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            // The Monday the week starts on.
            $table->date('week');
            $table->unsignedSmallInteger('tier');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['week', 'tier']);
        });

        Schema::create('league_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp')->default(0);
            $table->boolean('promoted')->default(false);
            // A day over the cap, or in the future, was seen: Hillel looks.
            $table->timestamp('flagged_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_members');
        Schema::dropIfExists('leagues');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['league_joined_at', 'league_tier']);
        });
    }
};
