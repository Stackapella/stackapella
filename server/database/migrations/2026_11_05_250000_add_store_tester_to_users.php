<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The accounts whose sandbox store purchases (TestFlight, App Review, Google's test purchases)
 * give Plus: marked by hand (php artisan app:store:tester), so a public TestFlight is never free
 * Plus for everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('store_tester_at')->nullable()->after('store_plus_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('store_tester_at');
        });
    }
};
