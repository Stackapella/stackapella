<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Breadth (docs/retention-plan.md, section 8): the features a signed-in singer has tried, each with the day it was
 * first used, so the app's "try next" line follows them to another device. Theirs alone: returned with the account,
 * never listed in Admin, gone with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->json('tried')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('tried'));
    }
};
