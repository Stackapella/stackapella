<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A share given a new mix by "Update that link" (2026-11-23) had its JSON columns copied raw, so encoded twice (a JSON
 * string holding the list). This decodes any such value once; one already right is left as it is, so running it again
 * changes nothing.
 */
return new class extends Migration
{
    private const COLUMNS = ['tracks', 'guide_ids', 'ai_parts', 'own_voice_parts', 'own_voice_friends', 'take_ids', 'singer_ids'];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            if (Schema::hasColumn('harmonies', $column)) {
                DB::statement("update harmonies set `{$column}` = cast(json_unquote(`{$column}`) as json) where `{$column}` is not null and json_valid(`{$column}`) and json_type(`{$column}`) = 'STRING' and json_valid(json_unquote(`{$column}`))");
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: only values that were wrong were changed.
    }
};
