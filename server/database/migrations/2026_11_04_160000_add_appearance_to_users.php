<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The theme a person chose (light, dark or their device's, and a colour), so it follows them
    // to a new device. Null until they choose. A nullable column, safe on the live database.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('appearance')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
