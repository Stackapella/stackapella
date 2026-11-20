<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shared harmony's parts that are AI voices in the sharer's own voice (Hear yourself as a choir), by part name, kept
 * on the share itself so its page labels them as AI even after the singer deletes their choir song. Nullable: safe on
 * the live database, and to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('harmonies', 'own_voice_parts')) {
            return;
        }

        Schema::table('harmonies', function (Blueprint $table): void {
            $table->json('own_voice_parts')->nullable()->after('guide_ids');
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table): void {
            $table->dropColumn('own_voice_parts');
        });
    }
};
