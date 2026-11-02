<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lend your voice (docs/lend-your-voice-plan.md): a volunteer is a beta singer, unpaid and with no
 * invite link; a claim is an assignment they took themselves, held for a while. Live-safe: columns
 * at the end, nullable or with a default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beta_singers', function (Blueprint $table): void {
            $table->boolean('volunteer')->default(false);
        });

        Schema::table('beta_assignments', function (Blueprint $table): void {
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('beta_assignments', function (Blueprint $table): void {
            $table->dropColumn(['claimed_at', 'expires_at']);
        });

        Schema::table('beta_singers', function (Blueprint $table): void {
            $table->dropColumn('volunteer');
        });
    }
};
