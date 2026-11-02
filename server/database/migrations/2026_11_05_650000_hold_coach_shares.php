<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hillel: "Hold 7 days, reverse on dispute" (2026-11-01). When a coach's share was taken back for a
 * dispute, so a share paid again after a dispute is won is a new transfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table): void {
            $table->timestamp('transfer_reversed_at')->nullable()->after('stripe_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('engagements', fn (Blueprint $table) => $table->dropColumn('transfer_reversed_at'));
    }
};
