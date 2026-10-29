<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Sign-in for the native apps (the mobile agent's token-auth plan): each app sign-in is a
    // device, holding only hashes of its access and refresh tokens; and the refresh token Apple
    // gives an app sign-in, kept to revoke when the account is deleted. A new table and a nullable
    // column: safe on the live database.
    public function up(): void
    {
        Schema::create('app_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('platform', 12);
            $table->string('model', 80)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->char('access_hash', 64)->unique();
            $table->timestamp('access_expires_at');
            $table->char('refresh_hash', 64)->unique();
            $table->char('previous_refresh_hash', 64)->nullable()->index();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamp('refresh_expires_at');
            $table->string('method', 16);
            $table->timestamp('signed_in_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('apple_refresh_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('apple_refresh_token');
        });

        Schema::dropIfExists('app_devices');
    }
};
