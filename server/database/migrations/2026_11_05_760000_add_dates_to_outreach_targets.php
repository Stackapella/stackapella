<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-sensitive outreach (2026-11-02): a date to send by (the email's wording goes stale after it,
 * "before the festival"), and a day its follow-up must wait for (after the event), both in the
 * report's days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_targets', function (Blueprint $table): void {
            $table->date('send_by')->nullable()->after('angle');
            $table->date('follow_up_not_before')->nullable()->after('send_by');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_targets', fn (Blueprint $table) => $table->dropColumn(['send_by', 'follow_up_not_before']));
    }
};
