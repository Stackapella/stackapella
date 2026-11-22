<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The singer's original clip, kept with their voice (Hillel, 2026-11-23: "useful for people to compare the
 * before/after"): an AAC copy, private, deleted with the voice. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('own_choir_voices', 'clip_key')) {
            Schema::table('own_choir_voices', function (Blueprint $table): void {
                $table->string('clip_key')->nullable()->after('reference_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_voices', 'clip_key')) {
            Schema::table('own_choir_voices', function (Blueprint $table): void {
                $table->dropColumn('clip_key');
            });
        }
    }
};
