<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hillel, 2026-11-11 (docs/retention-plan.md, Q8: "Keep the song's count only"): no account keeps the shared song it
 * signed up from. The harmony's anonymous sign_ups count stays; the column stays empty (ShareSignUps no longer
 * writes it).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('signup_harmony_id')->update(['signup_harmony_id' => null]);
    }

    public function down(): void
    {
        // What was cleared isn't kept anywhere, so there's nothing to put back.
    }
};
