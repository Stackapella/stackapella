<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lend your voice, slice 3 (docs/lend-your-voice-plan.md): a volunteer whose take is refused, or
 * whose published voice is removed, for abuse (someone else's recording, a sample, offensive
 * content) can't claim again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beta_singers', function (Blueprint $table): void {
            $table->timestamp('volunteer_blocked_at')->nullable()->after('volunteer');
        });
    }

    public function down(): void
    {
        Schema::table('beta_singers', fn (Blueprint $table) => $table->dropColumn('volunteer_blocked_at'));
    }
};
