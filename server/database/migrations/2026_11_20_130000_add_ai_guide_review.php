<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hillel's review of each AI guide (docs/ai-voices-build-plan.md, phase 1c): on the AI upload (pending, approved or
 * rejected, his note, when and by whom), which outlives a rejected guide's file; and on the guide when it was approved,
 * which is what lets it reach the app. Nullable columns: safe on the live database, and to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ai_uploads', 'review')) {
            Schema::table('ai_uploads', function (Blueprint $table): void {
                $table->string('review', 16)->nullable()->after('failure');
                $table->string('review_note', 500)->nullable()->after('review');
                $table->timestamp('reviewed_at')->nullable()->after('review_note');
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('template_guides', 'approved_at')) {
            Schema::table('template_guides', function (Blueprint $table): void {
                $table->timestamp('approved_at')->nullable()->after('ai_voice_id');
            });
        }

        // AI guides published before the review existed wait for it, as every new one does (none were ever approved).
        DB::table('ai_uploads')->whereNull('review')->where('status', 'published')->whereNotNull('template_guide_id')->update(['review' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('template_guides', function (Blueprint $table): void {
            $table->dropColumn('approved_at');
        });
        Schema::table('ai_uploads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review', 'review_note', 'reviewed_at']);
        });
    }
};
