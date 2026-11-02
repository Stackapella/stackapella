<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real voices across a breath (2026-11-01): when a new version only shortens notes to give singers
 * a breath (the same starts and pitches), a take recorded for the older one carries over with
 * those moments silenced as its guide is made. Each is a span of song beats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beta_submissions', function (Blueprint $table): void {
            $table->json('rests')->nullable()->after('gain_db');
        });
    }

    public function down(): void
    {
        Schema::table('beta_submissions', fn (Blueprint $table) => $table->dropColumn('rests'));
    }
};
