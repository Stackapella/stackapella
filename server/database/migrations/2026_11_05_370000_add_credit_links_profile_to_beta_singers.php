<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A beta singer credited by name may link that credit to their public profile (the landing agent's
 * proposal, agreed): off until they choose it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beta_singers', function (Blueprint $table): void {
            $table->boolean('credit_links_profile')->default(false)->after('credit_mode');
        });
    }

    public function down(): void
    {
        Schema::table('beta_singers', function (Blueprint $table): void {
            $table->dropColumn('credit_links_profile');
        });
    }
};
