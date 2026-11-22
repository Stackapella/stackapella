<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A choir clip is always the tune, sung in any octave (2026-11-23): a kept voice remembers the octave it was sung in
 * and the notes it actually sang (the worker's report), its range for a choir with friends; a request remembers the
 * octave the app measured, checked against the worker's. Guarded; safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('own_choir_voices', function (Blueprint $table): void {
            if (! Schema::hasColumn('own_choir_voices', 'sung_low')) {
                $table->tinyInteger('octave')->nullable();
                $table->unsignedTinyInteger('sung_low')->nullable();
                $table->unsignedTinyInteger('sung_high')->nullable();
            }
        });

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('own_choir_requests', 'octave')) {
                $table->tinyInteger('octave')->nullable()->after('offset_frames');
            }
        });
    }

    public function down(): void
    {
        Schema::table('own_choir_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('own_choir_requests', 'octave')) {
                $table->dropColumn('octave');
            }
        });

        Schema::table('own_choir_voices', function (Blueprint $table): void {
            if (Schema::hasColumn('own_choir_voices', 'sung_low')) {
                $table->dropColumn(['octave', 'sung_low', 'sung_high']);
            }
        });
    }
};
