<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "When were you born?" (Hillel's mockup): the month and year only, never a day. 13 and over, and
 * 18 and over, come from them (Age), into age_confirmed_at and adult_confirmed_at as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('birth_month')->nullable()->after('adult_confirmed_at');
            $table->unsignedSmallInteger('birth_year')->nullable()->after('birth_month');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['birth_month', 'birth_year']);
        });
    }
};
