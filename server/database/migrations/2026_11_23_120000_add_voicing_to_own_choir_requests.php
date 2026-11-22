<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The low-voice version (Hillel, 2026-11-23): a song in a low voice renders its treble parts an octave down. Every
 * existing request was rendered as written; and the clip's tune transposed to the singer's first note. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('own_choir_requests', 'voicing')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->string('voicing', 10)->default('written')->after('octave');
                // The clip's tune transposed to the singer's comfortable first note, in semitones (2026-11-23).
                $table->tinyInteger('clip_transpose')->default(0)->after('clip_template_version_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'voicing')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropColumn(['voicing', 'clip_transpose']);
            });
        }
    }
};
