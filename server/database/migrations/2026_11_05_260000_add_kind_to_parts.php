<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instrument parts (docs/instrument-parts-plan.md): a project's part is a voice or an instrument,
 * for the credits ("Sung by", "Played by"). Every part made before is a voice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->string('kind', 12)->default('voice')->after('template_part_key');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
