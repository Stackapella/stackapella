<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Which takes were in a shared harmony's mix, so only those can become a guide voice (the
    // review of N1). A nullable column: safe on the live database; older harmonies stay null.
    public function up(): void
    {
        Schema::table('harmonies', function (Blueprint $table) {
            $table->json('take_ids')->nullable()->after('singer_ids');
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table) {
            $table->dropColumn('take_ids');
        });
    }
};
