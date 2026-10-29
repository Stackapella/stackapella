<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // An exercise pack is sold, and shown, as Hillel approved it: its description, price and
    // currency are kept at approval with its title and exercises. A pack approved as it stands
    // keeps its own; one changed since approval waits for its next review. Nullable, safe live.
    public function up(): void
    {
        Schema::table('exercise_packs', function (Blueprint $table) {
            $table->text('approved_description')->nullable()->after('approved_exercises');
            $table->unsignedInteger('approved_price_cents')->nullable()->after('approved_description');
            $table->char('approved_currency', 3)->nullable()->after('approved_price_cents');
        });

        DB::table('exercise_packs')->where('status', 'approved')->update([
            'approved_description' => DB::raw('description'),
            'approved_price_cents' => DB::raw('price_cents'),
            'approved_currency' => DB::raw('currency'),
        ]);
    }

    public function down(): void
    {
        Schema::table('exercise_packs', function (Blueprint $table) {
            $table->dropColumn(['approved_description', 'approved_price_cents', 'approved_currency']);
        });
    }
};
