<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An organization's research note (2026-11-02): what marketing found about it, each specific with the
 * page it came from, shown on its page in Admin beside the draft written from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_targets', function (Blueprint $table): void {
            $table->text('research_note')->nullable()->after('notes');
            $table->json('research_links')->nullable()->after('research_note');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_targets', fn (Blueprint $table) => $table->dropColumn(['research_note', 'research_links']));
    }
};
