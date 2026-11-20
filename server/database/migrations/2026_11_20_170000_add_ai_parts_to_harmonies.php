<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A shared harmony's catalogue AI parts (docs/ai-voices-build-plan.md), kept on the share itself when it's made: each
 * part and the AI voice that sang it. The guide row can go (a real take replaces it, the voice is withdrawn) while the
 * shared mix still holds the AI singing, so its page must still say so. Filled for shares made before it from the AI
 * guides still there. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('harmonies', 'ai_parts')) {
            Schema::table('harmonies', function (Blueprint $table): void {
                $table->json('ai_parts')->nullable()->after('guide_ids');
            });
        }

        $ai = DB::table('template_guides')->where('source', 'ai')->get(['id', 'part_key', 'ai_voice_id', 'template_version_id'])->keyBy('id');

        if ($ai->isEmpty()) {
            return;
        }

        DB::table('harmonies')->whereNull('ai_parts')->whereNotNull('template_version_id')->orderBy('id')->chunkById(500, function ($harmonies) use ($ai): void {
            foreach ($harmonies as $harmony) {
                $parts = [];

                foreach ((array) json_decode((string) $harmony->guide_ids, true) as $id) {
                    $guide = $ai->get((int) $id);

                    if ($guide !== null && (int) $guide->template_version_id === (int) $harmony->template_version_id) {
                        $parts[] = ['part' => (string) $guide->part_key, 'voice' => $guide->ai_voice_id === null ? null : (int) $guide->ai_voice_id];
                    }
                }

                if ($parts !== []) {
                    DB::table('harmonies')->where('id', $harmony->id)->update(['ai_parts' => json_encode($parts)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table): void {
            $table->dropColumn('ai_parts');
        });
    }
};
