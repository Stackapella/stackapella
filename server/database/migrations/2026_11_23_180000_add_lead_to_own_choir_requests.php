<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The singer's own recording as the lead (Hillel, 2026-11-23): the whole song sung, or Happy Birthday from the
 * voice-capture clip; the key the song is rendered in so it lines up; and which real recordings a share holds, for its
 * takedown. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('own_choir_requests', 'lead')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->json('lead')->nullable()->after('remake');
                $table->string('lead_upload_key')->nullable()->after('lead');
                $table->string('lead_key')->nullable()->after('lead_upload_key');
                $table->tinyInteger('key_shift')->default(0)->after('lead_key');
            });
        }

        if (! Schema::hasColumn('harmonies', 'real_voice')) {
            Schema::table('harmonies', function (Blueprint $table): void {
                $table->json('real_voice')->nullable()->after('own_voice_friends');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('harmonies', 'real_voice')) {
            Schema::table('harmonies', function (Blueprint $table): void {
                $table->dropColumn('real_voice');
            });
        }

        if (Schema::hasColumn('own_choir_requests', 'lead')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropColumn(['lead', 'lead_upload_key', 'lead_key', 'key_shift']);
            });
        }
    }
};
