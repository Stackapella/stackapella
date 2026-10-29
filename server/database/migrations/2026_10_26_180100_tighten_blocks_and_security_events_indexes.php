<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two fixes from M1's review:
 * - blocks.value compares exactly: Stripe card fingerprints are case-sensitive (emails, domains
 *   and IPs are stored in one lower-case spelling, so they still match);
 * - the admin lists filter security events by type or person and sort by id, so the indexes say so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->string('value', 255)->collation('utf8mb4_bin')->change();
        });

        Schema::table('security_events', function (Blueprint $table) {
            $table->index(['type', 'id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->dropIndex(['type', 'id']);
            $table->dropIndex(['user_id', 'id']);
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->string('value', 255)->collation('utf8mb4_unicode_ci')->change();
        });
    }
};
