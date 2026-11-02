<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Query speed for Admin's counts (the active singers, the hourly bars, sign-ups by day and month):
 * each reads a time range of takes, scores or accounts, which had no index to start from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('takes', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('scores', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('users', fn (Blueprint $table) => $table->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('takes', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('scores', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['created_at']));
    }
};
