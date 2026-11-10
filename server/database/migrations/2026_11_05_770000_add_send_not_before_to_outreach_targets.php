<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The first email's earliest day (a busy week, two emails spaced apart, or held for the lawyer), and
 * the email's timing note, shown where Hillel presses Send (some are checks to make on the day).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_targets', function (Blueprint $table): void {
            $table->date('send_not_before')->nullable()->after('send_by');
            $table->string('timing_note', 1000)->nullable()->after('follow_up_not_before');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_targets', fn (Blueprint $table) => $table->dropColumn(['send_not_before', 'timing_note']));
    }
};
