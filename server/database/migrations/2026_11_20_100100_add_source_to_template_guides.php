<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A guide's source (docs/ai-voices-build-plan.md, phase 1): `recorded` (a real singer's take) or `ai`, and the AI voice
 * that sang an AI one. Every guide before this is recorded: the column defaults to it, and any row still without one is
 * filled. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('template_guides', 'source')) {
            Schema::table('template_guides', function (Blueprint $table): void {
                $table->string('source', 16)->default('recorded')->after('part_key');
            });
        }

        if (! Schema::hasColumn('template_guides', 'ai_voice_id')) {
            Schema::table('template_guides', function (Blueprint $table): void {
                $table->foreignId('ai_voice_id')->nullable()->after('source')->constrained('ai_voices')->restrictOnDelete();
            });
        }

        DB::table('template_guides')->where(fn ($query) => $query->whereNull('source')->orWhere('source', ''))->update(['source' => 'recorded']);
    }

    public function down(): void
    {
        Schema::table('template_guides', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_voice_id');
            $table->dropColumn('source');
        });
    }
};
