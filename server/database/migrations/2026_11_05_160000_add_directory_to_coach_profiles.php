<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The coach directory (N4; docs/coaching-marketplace-plan.md, F7): Hillel's featured coaches,
    // and what the directory sorts and filters on, worked out hourly from each coach's offerings
    // and Event Schedule. Nullable columns: safe on the live database.
    public function up(): void
    {
        Schema::table('coach_profiles', function (Blueprint $table) {
            $table->timestamp('featured_at')->nullable()->after('approved_at');
            $table->unsignedInteger('from_cents')->nullable()->after('featured_at');
            $table->char('from_currency', 3)->nullable()->after('from_cents');
            $table->unsignedSmallInteger('turnaround_days')->nullable()->after('from_currency');
            $table->timestamp('next_lesson_at')->nullable()->after('turnaround_days');
            $table->timestamp('directory_refreshed_at')->nullable()->after('next_lesson_at');

            $table->index(['status', 'featured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('coach_profiles', function (Blueprint $table) {
            $table->dropIndex(['status', 'featured_at']);
            $table->dropColumn(['featured_at', 'from_cents', 'from_currency', 'turnaround_days', 'next_lesson_at', 'directory_refreshed_at']);
        });
    }
};
