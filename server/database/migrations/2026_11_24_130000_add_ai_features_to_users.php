<?php

declare(strict_types=1);

use App\Support\Accounts\Age;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The AI features setting (docs/ai-optional-plan.md v2): null until chosen (off for an account made from now on), the
 * choice, when and where it came from; every account made before this keeps AI on (source existing). And the age band,
 * kept from the birth date once given so a cohort doesn't move on a birthday; existing accounts with a birth date get it
 * now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'ai_features')) {
                $table->boolean('ai_features')->nullable();
                $table->timestamp('ai_features_at')->nullable();
                // Where the choice came from: card, settings, ai-singing, or existing (accounts made before it shipped).
                $table->string('ai_features_source', 16)->nullable();
            }

            if (! Schema::hasColumn('users', 'age_band')) {
                $table->string('age_band', 8)->nullable();
            }
        });

        // Accounts made before AI became a choice keep it on, as they have it today (Hillel, the plan's v2): only those
        // never chosen, so a second run changes nothing.
        DB::table('users')->whereNull('ai_features')->whereNull('ai_features_source')
            ->update(['ai_features' => true, 'ai_features_source' => 'existing', 'ai_features_at' => now()]);

        // Only what's left: a band never replaced once set.
        DB::table('users')->whereNull('age_band')->whereNotNull('birth_month')->whereNotNull('birth_year')->orderBy('id')->select(['id', 'birth_month', 'birth_year'])
            ->chunkById(500, static function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update(['age_band' => Age::band((int) $user->birth_month, (int) $user->birth_year)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['ai_features', 'ai_features_at', 'ai_features_source', 'age_band']);
        });
    }
};
