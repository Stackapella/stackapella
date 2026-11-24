<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The worker's pitch numbers for a job (worker.choir.jobs.finish `gateFailures[].cents` and `partWorst`): the worst
 * notes in signed cents, numbers only, for Admin > Choir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'gate_numbers')) {
            return;
        }

        Schema::table('own_choir_requests', function (Blueprint $table): void {
            $table->json('gate_numbers')->nullable()->after('failure_numbers');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('own_choir_requests', 'gate_numbers')) {
            Schema::table('own_choir_requests', function (Blueprint $table): void {
                $table->dropColumn('gate_numbers');
            });
        }
    }
};
