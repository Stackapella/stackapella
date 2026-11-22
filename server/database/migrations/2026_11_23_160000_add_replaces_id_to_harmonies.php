<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Update that link" (Hillel's share bug, 2026-11-23): a new mix for an existing share is uploaded as a hidden row that
 * names the share it replaces; once processed it's folded into that share (same address) and the row goes. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('harmonies', 'replaces_id')) {
            Schema::table('harmonies', function (Blueprint $table): void {
                $table->foreignId('replaces_id')->nullable()->after('parent_id')->constrained('harmonies')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('harmonies', 'replaces_id')) {
            Schema::table('harmonies', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('replaces_id');
            });
        }
    }
};
