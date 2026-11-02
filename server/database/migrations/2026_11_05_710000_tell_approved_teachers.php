<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher hears once that their class codes work (the coordinator, 2026-11-02): when Admin first
 * approves them, never again if approval is taken back and given again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_teachers', function (Blueprint $table): void {
            $table->timestamp('told_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('school_teachers', fn (Blueprint $table) => $table->dropColumn('told_at'));
    }
};
