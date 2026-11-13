<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kept take a volunteer offered as a guide (voices.share; docs/open-guide-voices-plan.md), so the same take is never
 * sent twice. Null for every take sent before (recorded on My parts).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('beta_submissions', 'take_id')) {
            return;
        }

        Schema::table('beta_submissions', function (Blueprint $table): void {
            $table->foreignId('take_id')->nullable()->after('beta_assignment_id')->constrained('takes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('beta_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('take_id');
        });
    }
};
