<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The country someone last signed in from (Hillel: "can we track our users' countries"): two
 * letters from Cloudflare's CF-IPCountry, the latest only, never an address or a place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('country', 2)->nullable()->after('heard_from')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('country');
        });
    }
};
