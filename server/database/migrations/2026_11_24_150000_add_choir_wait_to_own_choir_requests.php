<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The choir wait (Hillel, 2026-11-24): a song the app started by itself waits before the renderer may take it; one
 * email when it's ready or couldn't be made, and each account's say in it (on by default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('own_choir_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('own_choir_requests', 'hold_until')) {
                $table->timestamp('hold_until')->nullable();
                $table->boolean('auto')->default(false);
                $table->timestamp('email_sent_at')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'choir_email')) {
                $table->boolean('choir_email')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('own_choir_requests', function (Blueprint $table): void {
            $table->dropColumn(['hold_until', 'auto', 'email_sent_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('choir_email');
        });
    }
};
