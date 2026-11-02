<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asking a beta singer to record a part again (2026-11-01): when a new version of a song changes
 * their part, Hillel asks again in one tap with a short note; My parts shows it as a retake until
 * they send a new take.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beta_assignments', function (Blueprint $table): void {
            $table->timestamp('asked_again_at')->nullable()->after('expires_at');
            $table->string('asked_again_note', 500)->nullable()->after('asked_again_at');
        });
    }

    public function down(): void
    {
        Schema::table('beta_assignments', fn (Blueprint $table) => $table->dropColumn(['asked_again_at', 'asked_again_note']));
    }
};
