<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The singing test's boards (docs/singing-test-v1.md): a test held for a look (a jump, flat notes, the same items as
 * another), looked at, or taken off the boards; and its items' fingerprint, to find a copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('singing_tests', function (Blueprint $table): void {
            if (! Schema::hasColumn('singing_tests', 'items_hash')) {
                $table->char('items_hash', 64)->nullable()->index();
                $table->timestamp('flagged_at')->nullable();
                $table->json('flag_reasons')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('hidden_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('singing_tests', function (Blueprint $table): void {
            $table->dropColumn(['items_hash', 'flagged_at', 'flag_reasons', 'reviewed_at', 'hidden_at']);
        });
    }
};
