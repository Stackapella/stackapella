<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Someone who has said they're 18 or over, where that's asked (choir groups for now, coaching
    // next). A nullable column, so it's safe on the live database.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('adult_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('adult_confirmed_at'));
    }
};
