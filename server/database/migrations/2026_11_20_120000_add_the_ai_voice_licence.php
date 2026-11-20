<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI voice licence, agreed in the app (docs/ai-voices-build-plan.md, phase 1b): an admin's offer on the beta singer,
 * and on their AI voice the singer it's theirs and when they accepted (the terms' version is consent_version, the credit
 * choice `credited`, withdrawn withdrawn_at). Nullable columns: safe on the live database, and to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('beta_singers', 'ai_licence_offered_at')) {
            Schema::table('beta_singers', function (Blueprint $table): void {
                $table->timestamp('ai_licence_offered_at')->nullable();
            });
        }

        if (! Schema::hasColumn('ai_voices', 'beta_singer_id')) {
            Schema::table('ai_voices', function (Blueprint $table): void {
                $table->foreignId('beta_singer_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('ai_voices', 'accepted_at')) {
            Schema::table('ai_voices', function (Blueprint $table): void {
                $table->timestamp('accepted_at')->nullable()->after('consent_version');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ai_voices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('beta_singer_id');
            $table->dropColumn('accepted_at');
        });
        Schema::table('beta_singers', function (Blueprint $table): void {
            $table->dropColumn('ai_licence_offered_at');
        });
    }
};
