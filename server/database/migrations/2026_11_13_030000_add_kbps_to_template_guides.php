<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bitrate each guide was encoded at (beta.guide_kbps), so app:guides:reencode knows which are still to do. Null for
 * every guide before it: they were all 128 kbps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('template_guides', 'kbps')) {
            return;
        }

        Schema::table('template_guides', function (Blueprint $table): void {
            $table->unsignedSmallInteger('kbps')->nullable()->after('gain');
        });
    }

    public function down(): void
    {
        Schema::table('template_guides', function (Blueprint $table): void {
            $table->dropColumn('kbps');
        });
    }
};
