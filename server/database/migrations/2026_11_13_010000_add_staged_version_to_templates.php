<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A library song's staged next version (docs/decisions.md, 2026-11-12, Amazing Grace): the content's `<id>.next.json`,
 * kept beside the live one and never current, so new recordings target it before the switch. Set by the templates
 * sync; null for every song now.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('templates', 'staged_version_id')) {
            return;
        }

        Schema::table('templates', function (Blueprint $table): void {
            $table->foreignId('staged_version_id')->nullable()->after('current_version_id')->constrained('template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('staged_version_id');
        });
    }
};
