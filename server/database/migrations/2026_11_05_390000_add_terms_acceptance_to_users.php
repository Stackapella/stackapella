<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which terms of use an account agreed to, and when (B, the launch audit): set when it's made, and
 * again when it accepts a new version. Accounts from before stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('terms_version')->nullable()->after('age_confirmed_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['terms_version', 'terms_accepted_at']);
        });
    }
};
