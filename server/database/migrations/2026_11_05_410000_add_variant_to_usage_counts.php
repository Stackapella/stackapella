<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The onboarding funnel's experiment arm on the anonymous counts (App\Actions\Counts\Funnel):
 * "onb1-b", or '' for none, so the key stays unique. A variant is shared by half of all devices;
 * nothing here names anyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->string('variant', 24)->default('')->after('country');
            $table->dropUnique(['day', 'kind', 'audience', 'country']);
            $table->unique(['day', 'kind', 'audience', 'country', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::table('usage_counts', function (Blueprint $table): void {
            $table->dropUnique(['day', 'kind', 'audience', 'country', 'variant']);
            $table->unique(['day', 'kind', 'audience', 'country']);
            $table->dropColumn('variant');
        });
    }
};
