<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the last wrong code was typed, so the count of wrong codes starts again after a quiet day
 * (one typo weeks after a lockout shouldn't lock the real admin out again).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_second_factors', function (Blueprint $table) {
            $table->timestamp('last_failed_at')->nullable()->after('failed_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('admin_second_factors', function (Blueprint $table) {
            $table->dropColumn('last_failed_at');
        });
    }
};
