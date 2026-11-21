<?php

declare(strict_types=1);

use App\Support\OwnChoir\OwnChoir;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a choir song costs (the GPU review, 2026-11-21): its part-minutes and the GPU time its claims spent, whether it's
 * the singer's own song, who goes first (paid Plus), when it may start on a busy day, its claim's start (a claim never
 * lasts past an hour), and the email it's counted against (a keyed hash, never the address); each part's listening
 * copy. Guarded, and the part-minutes of songs already made filled in; safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('own_choir_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('own_choir_requests', 'part_minutes')) {
                $table->decimal('part_minutes', 8, 2)->default(0)->after('tries');
                $table->decimal('gpu_seconds', 10, 1)->default(0)->after('part_minutes');
                $table->decimal('claim_gpu_seconds', 10, 1)->default(0)->after('gpu_seconds');
                $table->boolean('own')->default(false)->after('claim_gpu_seconds');
                $table->unsignedTinyInteger('priority')->default(0)->after('own');
                $table->timestamp('starts_after')->nullable()->after('priority');
                $table->timestamp('claim_started_at')->nullable()->after('starts_after');
                $table->timestamp('first_claimed_at')->nullable()->index()->after('claim_started_at');
                $table->char('email_key', 64)->nullable()->index()->after('first_claimed_at');
            }
        });

        Schema::table('own_choir_parts', function (Blueprint $table): void {
            if (! Schema::hasColumn('own_choir_parts', 'listen_key')) {
                $table->string('listen_key')->nullable()->after('media_key');
            }
        });

        // Songs asked for before: their part-minutes from their version's notes, once (rows still at 0).
        DB::table('own_choir_requests')->where('part_minutes', 0)->whereNotNull('template_version_id')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $data = json_decode((string) DB::table('template_versions')->where('id', $row->template_version_id)->value('data'), true);
                $minutes = is_array($data) ? OwnChoir::partMinutes($data) : 0.0;
                DB::table('own_choir_requests')->where('id', $row->id)->where('part_minutes', 0)->update(['part_minutes' => $minutes]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('own_choir_parts', function (Blueprint $table): void {
            if (Schema::hasColumn('own_choir_parts', 'listen_key')) {
                $table->dropColumn('listen_key');
            }
        });

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('own_choir_requests', 'part_minutes')) {
                $table->dropIndex(['first_claimed_at']);
                $table->dropIndex(['email_key']);
                $table->dropColumn(['part_minutes', 'gpu_seconds', 'claim_gpu_seconds', 'own', 'priority', 'starts_after', 'claim_started_at', 'first_claimed_at', 'email_key']);
            }
        });
    }
};
