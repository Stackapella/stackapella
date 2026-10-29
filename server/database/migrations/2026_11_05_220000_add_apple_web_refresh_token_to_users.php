<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The web's Sign in with Apple keeps Apple's refresh token too (encrypted), so deleting an account
 * revokes Apple's tokens however it signed in: the web's are revoked with the Services ID, the
 * apps' (apple_refresh_token) with the bundle id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('apple_web_refresh_token')->nullable()->after('apple_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('apple_web_refresh_token');
        });
    }
};
