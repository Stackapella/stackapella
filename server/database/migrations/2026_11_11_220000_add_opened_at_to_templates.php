<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a library song opened (docs/retention-plan.md, row 9: Home's "New and in season"): set by the sync the first
 * time a version of it is reviewed. A song already open takes the day its current version was stored (versions keep
 * no later date; an in-place opening, as the carols' on 2026-11-10, reads a few days early). Safe to run again: a run
 * stopped after adding the column (MySQL can't undo DDL) only fills in what's left.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('templates', 'opened_at')) {
            Schema::table('templates', fn (Blueprint $table) => $table->dateTime('opened_at')->nullable()->after('current_version_id'));
        }

        DB::table('templates')->whereNull('owner_id')->whereNull('opened_at')->whereNotNull('current_version_id')->orderBy('id')->each(static function (object $template): void {
            $created = DB::table('template_versions')->where('id', $template->current_version_id)->where('reviewed', true)->value('created_at');

            if ($created !== null) {
                DB::table('templates')->where('id', $template->id)->update(['opened_at' => $created]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('templates', 'opened_at')) {
            Schema::table('templates', fn (Blueprint $table) => $table->dropColumn('opened_at'));
        }
    }
};
