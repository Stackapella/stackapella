<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner codes (docs/outreach-plan.md, "The partner offer"; Hillel, 2026-11-02): school codes made
 * general. Admin makes one for an approved partner organization with the seats it needs (no lifetime
 * cap), linked to its outreach target; each member redeems once for a year, under-18s under the
 * school rules, and nobody learns who.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->string('kind', 8)->default('class')->after('id');
            $table->foreignId('outreach_target_id')->nullable()->after('teacher_id')->constrained()->nullOnDelete();
            // A partner's membership can be in the tens of thousands.
            $table->unsignedInteger('seats')->change();
            $table->unsignedInteger('used')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('outreach_target_id');
            $table->dropColumn('kind');
        });
    }
};
